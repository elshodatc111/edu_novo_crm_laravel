<?php

namespace Tests\Feature;

use App\Enums\PayMethod;
use App\Enums\Role;
use App\Enums\Wallet;
use App\Models\CashRequest;
use App\Models\Payment;
use App\Services\CashboxService;
use App\Services\ConfirmationService;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * V7: ikki bosqichli tasdiqlash (chiqim, xarajat, ehson chiqimi, ish haqi), ehson naqt/plastik,
 * qarzdorni istisno tariqasida qo'shish va bir nechta to'lov bilan chegirma.
 */
class V7MoneyFlowTest extends TestCase
{
    use RefreshDatabase;

    private $branch;
    private $admin;
    private array $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00');
        $this->branch = $this->branch('Toshkent');
        $this->admin = $this->user(Role::Admin, $this->branch, ['groups.create', 'groups.members', 'groups.view', 'students.view', 'payments.view', 'payments.create', 'cashbox.view', 'cashbox.request', 'cashbox.approve', 'finance.view', 'finance.manage', 'teachers.view', 'teachers.pay', 'staff.pay', 'groups.enroll_debtor']);
        $this->cat = $this->catalog($this->branch);
    }

    private function bal(Wallet $w): int
    {
        return app(WalletService::class)->balance($this->branch->id, $w);
    }

    // ---------------------------------------------------------------- 1) ikki bosqichli tasdiqlash

    public function test_first_step_moves_no_money_and_confirmation_executes_once(): void
    {
        $this->fund($this->branch, Wallet::TreasuryCash, 500000);
        $this->actingAs($this->admin);

        $url = $this->post('/finance/expense', ['method' => 'cash', 'amount' => 100000, 'description' => 'Ijara'])->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString('/confirm/', $url);
        $this->assertSame(500000, $this->bal(Wallet::TreasuryCash));            // 1-bosqichda pul harakatlanmadi

        $this->get($url)->assertOk()->assertSee('Tekshiring')->assertSee('Ijara')->assertSee('400 000', false);   // qoldiq oldindan ko'rsatiladi

        $this->post($url)->assertRedirect(route('finance.index'))->assertSessionHas('success');
        $this->assertSame(400000, $this->bal(Wallet::TreasuryCash));

        // Qayta bosish (yoki orqaga qaytib yana yuborish) ikkinchi marta bajarmaydi
        $this->post($url)->assertRedirect(route('dashboard'))->assertSessionHas('error');
        $this->assertSame(400000, $this->bal(Wallet::TreasuryCash));
        $this->get($url)->assertRedirect(route('dashboard'));
    }

    public function test_confirmation_is_bound_to_user_and_expires(): void
    {
        $this->fund($this->branch, Wallet::TreasuryCash, 500000);
        $this->actingAs($this->admin);
        $url = $this->post('/finance/withdraw', ['source' => 'cash', 'amount' => 50000, 'description' => 'Chiqim'])->headers->get('Location');

        // Boshqa foydalanuvchi bu tasdiqni bajara olmaydi
        $other = $this->user(Role::Admin, $this->branch, ['finance.manage', 'finance.view']);
        $this->actingAs($other)->post($url)->assertRedirect(route('dashboard'))->assertSessionHas('error');
        $this->assertSame(500000, $this->bal(Wallet::TreasuryCash));

        // 15 daqiqadan keyin eskiradi
        $this->actingAs($this->admin);
        Carbon::setTestNow(now()->addMinutes(ConfirmationService::TTL_MINUTES + 1));
        $this->post($url)->assertRedirect(route('dashboard'))->assertSessionHas('error');
        $this->assertSame(500000, $this->bal(Wallet::TreasuryCash));
    }

    public function test_insufficient_funds_are_rejected_at_step_one_and_at_confirmation(): void
    {
        $this->fund($this->branch, Wallet::TreasuryCash, 100000);
        $this->actingAs($this->admin);

        $this->post('/finance/expense', ['method' => 'cash', 'amount' => 150000, 'description' => 'Ko\'p'])->assertSessionHasErrors('amount');

        // 1-bosqichdan keyin mablag' kamaysa, tasdiqlashda ham kassa manfiyga tushmaydi
        $url = $this->post('/finance/withdraw', ['source' => 'cash', 'amount' => 80000, 'description' => 'Chiqim'])->headers->get('Location');
        $this->fund($this->branch, Wallet::TreasuryCash, 20000);
        $this->post($url)->assertRedirect(route('finance.index'))->assertSessionHasErrors('amount');
        $this->assertSame(20000, $this->bal(Wallet::TreasuryCash));
    }

    public function test_payroll_needs_second_confirmation(): void
    {
        $this->fund($this->branch, Wallet::TreasuryCash, 500000);
        $this->actingAs($this->admin);
        $teacher = $this->cat['teacher'];

        $url = $this->post("/payroll/{$teacher->id}", ['method' => 'cash', 'amount' => 200000, 'description' => 'Avans'])->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString('/confirm/', $url);
        $this->assertSame(500000, $this->bal(Wallet::TreasuryCash));
        $this->assertSame(0, \App\Models\Payout::count());

        $this->get($url)->assertOk()->assertSee('Tekshiring');
        $this->post($url)->assertRedirect()->assertSessionHas('success');
        $this->assertSame(300000, $this->bal(Wallet::TreasuryCash));
        $this->assertSame(1, \App\Models\Payout::count());

        $this->post($url)->assertSessionHas('error');                                   // ikkinchi marta to'lanmaydi
        $this->assertSame(1, \App\Models\Payout::count());
    }

    public function test_confirmation_rechecks_permission(): void
    {
        $this->fund($this->branch, Wallet::TreasuryCash, 500000);
        $this->actingAs($this->admin);
        $url = $this->post('/finance/expense', ['method' => 'cash', 'amount' => 1000, 'description' => 'x'])->headers->get('Location');

        // Ruxsat 1- va 2-bosqich orasida olib tashlansa, bajarilmaydi
        \App\Models\UserPermission::where('user_id', $this->admin->id)->where('permission', 'finance.manage')->delete();
        $this->actingAs($this->admin->fresh())->post($url)->assertForbidden();
        $this->assertSame(500000, $this->bal(Wallet::TreasuryCash));
    }

    // ---------------------------------------------------------------- 2) ehson: naqt / plastik

    public function test_charity_is_split_by_method_and_withdrawn_separately(): void
    {
        $this->branch->update(['charity_percent' => 10]);
        $this->actingAs($this->admin);
        $cashbox = app(CashboxService::class);

        $this->fund($this->branch, Wallet::TillCash, 1000000);
        $this->fund($this->branch, Wallet::TillCard, 400000);
        $cashbox->approve($cashbox->request(CashRequest::WITHDRAWAL, PayMethod::Cash, 1000000, 'Naqt', $this->admin), $this->admin);
        $cashbox->approve($cashbox->request(CashRequest::WITHDRAWAL, PayMethod::Card, 400000, 'Plastik', $this->admin), $this->admin);

        $this->assertSame(100000, $this->bal(Wallet::TreasuryCharityCash));
        $this->assertSame(40000, $this->bal(Wallet::TreasuryCharityCard));

        // Moliya sahifasida umumiy ehson va uning naqt/plastik qismi ko'rinadi
        $this->get('/finance')->assertOk()->assertSee('Naqt ehson')->assertSee('Plastik ehson')->assertSee('140 000', false);

        // Naqt ehsondan chiqim: faqat naqt ehson kamayadi
        $url = $this->post('/finance/charity-withdraw', ['method' => 'cash', 'amount' => 30000, 'description' => 'Yordam'])->assertRedirect()->headers->get('Location');
        $this->assertSame(100000, $this->bal(Wallet::TreasuryCharityCash));
        $this->post($url)->assertSessionHas('success');
        $this->assertSame(70000, $this->bal(Wallet::TreasuryCharityCash));
        $this->assertSame(40000, $this->bal(Wallet::TreasuryCharityCard));

        // Plastik ehsondan naqt kabi ko'p yechib bo'lmaydi
        $this->post('/finance/charity-withdraw', ['method' => 'card', 'amount' => 50000, 'description' => 'Ko\'p'])->assertSessionHasErrors('amount');
    }

    // ---------------------------------------------------------------- 3) qarzdorni istisno tariqasida qo'shish

    private function debtorAndGroups(): array
    {
        $this->actingAs($this->admin);
        $g1 = app(GroupService::class)->create($this->groupPayload($this->cat, ['name' => 'G1']), $this->admin);
        $g2 = app(GroupService::class)->create($this->groupPayload($this->cat, ['name' => 'G2', 'schedule' => 'even', 'starts_on' => '2026-09-22']), $this->admin);
        $s = $this->student($this->branch);
        app(EnrollmentService::class)->enroll($g1, $s, null, $this->admin);      // -500 000

        return [$g1, $g2, $s];
    }

    public function test_debtor_can_be_added_only_as_exception_by_permitted_admin(): void
    {
        [, $g2, $s] = $this->debtorAndGroups();
        $this->actingAs($this->admin);

        // Oddiy qo'shish rad etiladi, istisno belgisi bilan qo'shiladi
        $this->post("/groups/{$g2->id}/students", ['student_id' => $s->id])->assertSessionHasErrors('student_id');
        $this->assertFalse($g2->activeMembers()->where('student_id', $s->id)->exists());

        $this->post("/groups/{$g2->id}/students", ['student_id' => $s->id, 'allow_debt' => 1])->assertSessionHas('success');
        $this->assertTrue($g2->activeMembers()->where('student_id', $s->id)->exists());
        $this->assertSame(-1000000, $s->fresh()->balance);                            // narxi yechildi, qarz oshdi

        // Jurnalda istisno qayd etiladi
        $this->assertTrue(\App\Models\BalanceTransaction::where('student_id', $s->id)->where('note', 'like', '%istisno%')->exists());
    }

    public function test_exception_needs_permission_manager_and_admin_without_it_are_refused(): void
    {
        [, $g2, $s] = $this->debtorAndGroups();

        $manager = $this->user(Role::Manager, $this->branch, ['groups.members', 'groups.view', 'students.view']);
        $this->actingAs($manager)->post("/groups/{$g2->id}/students", ['student_id' => $s->id, 'allow_debt' => 1])->assertSessionHasErrors('student_id');

        $noPerm = $this->user(Role::Admin, $this->branch, ['groups.members', 'groups.view', 'students.view']);
        $this->actingAs($noPerm)->post("/groups/{$g2->id}/students", ['student_id' => $s->id, 'allow_debt' => 1])->assertSessionHasErrors('student_id');
        $this->assertFalse($g2->activeMembers()->where('student_id', $s->id)->exists());

        // sAdmin hech qanday qo'shimcha ruxsatsiz qo'sha oladi
        $sadmin = $this->user(Role::SAdmin);
        $this->actingAs($sadmin)->withSession(['current_branch_id' => $this->branch->id])
            ->post("/groups/{$g2->id}/students", ['student_id' => $s->id, 'allow_debt' => 1])->assertSessionHas('success');
    }

    // ---------------------------------------------------------------- 4) bir nechta to'lov bilan chegirma

    public function test_early_discount_is_given_after_several_payments(): void
    {
        $this->actingAs($this->admin);
        $g = app(GroupService::class)->create($this->groupPayload($this->cat), $this->admin);     // narx 500 000, chegirma 50 000
        $s = $this->student($this->branch);
        app(EnrollmentService::class)->enroll($g, $s, null, $this->admin);
        $pay = app(PaymentService::class);

        $pay->receive($s, ['cash' => 200000], null, null, $this->admin);
        $pay->receive($s, ['card' => 150000], null, null, $this->admin);
        $this->assertSame(0, Payment::where('type', Payment::DISCOUNT)->count());               // 350 000 < 450 000: hali yetmadi
        $this->assertSame(-150000, $s->fresh()->balance);

        $pay->receive($s, ['cash' => 100000], null, null, $this->admin);                        // jami 450 000 = narx − chegirma
        $this->assertSame(1, Payment::where('type', Payment::DISCOUNT)->where('group_id', $g->id)->count());
        $this->assertSame(0, $s->fresh()->balance);                                             // -500 000 + 450 000 + 50 000 chegirma

        // Faqat bir marta
        $pay->receive($s, ['cash' => 10000], null, null, $this->admin);
        $this->assertSame(1, Payment::where('type', Payment::DISCOUNT)->count());
        $this->assertSame(10000, $s->fresh()->balance);
    }

    public function test_early_discount_is_not_given_when_total_debt_exceeds_group_price(): void
    {
        $this->actingAs($this->admin);
        $g1 = app(GroupService::class)->create($this->groupPayload($this->cat, ['name' => 'A1']), $this->admin);
        $g2 = app(GroupService::class)->create($this->groupPayload($this->cat, ['name' => 'A2', 'schedule' => 'even', 'starts_on' => '2026-09-22']), $this->admin);
        $s = $this->student($this->branch);
        $enroll = app(EnrollmentService::class);
        $enroll->enroll($g1, $s, null, $this->admin);
        $enroll->enroll($g2, $s, null, $this->admin, true);                                    // umumiy qarz 1 000 000 > guruh narxi 500 000

        // 450 000 to'lasa ham: birinchi guruh uchun talab bajarildi, lekin umumiy qarz (550 000) guruh narxidan (500 000) katta
        app(PaymentService::class)->receive($s, ['cash' => 450000], null, null, $this->admin);
        $this->assertSame(0, Payment::where('type', Payment::DISCOUNT)->count());

        // Yana 100 000: qarz 450 000 <= 500 000 -> birinchi guruh chegirma oladi (ikkinchisi uchun talab yig'ilgan: 900 000)
        app(PaymentService::class)->receive($s, ['cash' => 100000], null, null, $this->admin);
        $this->assertSame([$g1->id], Payment::where('type', Payment::DISCOUNT)->pluck('group_id')->all());
    }

    public function test_one_payment_is_not_counted_for_two_groups(): void
    {
        $this->actingAs($this->admin);
        $g1 = app(GroupService::class)->create($this->groupPayload($this->cat, ['name' => 'B1']), $this->admin);
        $g2 = app(GroupService::class)->create($this->groupPayload($this->cat, ['name' => 'B2', 'schedule' => 'even', 'starts_on' => '2026-09-22']), $this->admin);
        $s = $this->student($this->branch);
        $pay = app(PaymentService::class);
        $enroll = app(EnrollmentService::class);

        $pay->receive($s, ['cash' => 450000], null, null, $this->admin);                        // guruhsiz oldindan to'lov
        $enroll->enroll($g1, $s, null, $this->admin);                                           // oldindan to'lagan -> B1 chegirma oladi
        $this->assertSame([$g1->id], Payment::where('type', Payment::DISCOUNT)->pluck('group_id')->all());
        $this->assertSame(0, $s->fresh()->balance);

        // Xuddi shu 450 000 ikkinchi guruh uchun qayta hisoblanmaydi
        $enroll->enroll($g2, $s->fresh(), null, $this->admin, true);
        $this->assertSame([$g1->id], Payment::where('type', Payment::DISCOUNT)->pluck('group_id')->all());
        $this->assertSame(-500000, $s->fresh()->balance);

        // Yana 450 000 to'lasa (jami 900 000 = 2 × 450 000) endi B2 ham chegirma oladi
        $pay->receive($s, ['cash' => 450000], null, null, $this->admin);
        $this->assertEqualsCanonicalizing([$g1->id, $g2->id], Payment::where('type', Payment::DISCOUNT)->pluck('group_id')->all());
        $this->assertSame(0, $s->fresh()->balance);
    }
}
