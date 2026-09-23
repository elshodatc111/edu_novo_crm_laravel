<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\Wallet;
use App\Models\CashRequest;
use App\Models\Payment;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MoneyWebTest extends TestCase
{
    use RefreshDatabase;

    private $branch;
    private $admin;
    private $manager;
    private array $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00');

        $this->branch = $this->branch();
        $this->admin = $this->user(Role::Admin, $this->branch, ['groups.create', 'groups.members', 'groups.view', 'students.view', 'payments.view', 'payments.create', 'payments.discount', 'payments.refund', 'cashbox.view', 'cashbox.request', 'cashbox.approve', 'finance.view', 'finance.manage', 'teachers.view', 'teachers.pay', 'staff.view', 'staff.pay', 'settings.branch']);
        $this->manager = $this->user(Role::Manager, $this->branch, ['students.view', 'payments.view', 'payments.create', 'cashbox.view', 'cashbox.request']);
        $this->cat = $this->catalog($this->branch);
    }

    public function test_manager_receives_payment_through_web(): void
    {
        $student = $this->student($this->branch);

        $this->actingAs($this->manager)->post("/students/{$student->id}/payments", ['cash' => 250000, 'card' => 0, 'description' => 'Sentabr'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(250000, $student->fresh()->balance);
        $this->assertSame(250000, app(WalletService::class)->balance($this->branch->id, Wallet::TillCash));

        $this->actingAs($this->manager)->post("/students/{$student->id}/payments", ['cash' => 0, 'card' => 0])->assertSessionHasErrors('cash');
    }

    public function test_permissions_are_enforced(): void
    {
        $student = $this->student($this->branch);

        // Menejerda chegirma, qaytarish, tasdiqlash, moliya va ish haqi ruxsati yo'q
        $this->actingAs($this->manager)->post("/students/{$student->id}/discount", ['group_id' => 1, 'amount' => 1, 'description' => 'x'])->assertForbidden();
        $this->actingAs($this->manager)->post("/students/{$student->id}/refund", ['method' => 'cash', 'amount' => 1, 'description' => 'x'])->assertForbidden();
        $this->actingAs($this->manager)->get('/finance')->assertForbidden();
        $this->actingAs($this->manager)->get('/payroll')->assertForbidden();

        $req = app(\App\Services\CashboxService::class);
        $this->fund($this->branch, Wallet::TillCash, 100000);
        $this->actingAs($this->manager);
        $r = $req->request(CashRequest::EXPENSE, \App\Enums\PayMethod::Cash, 1000, 'x', $this->manager);
        $this->actingAs($this->manager)->post("/cashbox/requests/{$r->id}/approve")->assertForbidden();

        // Talaba to'lovini o'qituvchi ko'ra olmaydi
        $this->actingAs($this->cat['teacher'])->get('/payments')->assertForbidden();
        $this->actingAs($this->cat['teacher'])->get('/cashbox')->assertForbidden();
    }

    public function test_other_branch_data_is_hidden(): void
    {
        $other = $this->branch('Boshqa');
        $foreign = $this->student($other);

        $this->actingAs($this->manager)->post("/students/{$foreign->id}/payments", ['cash' => 1000])->assertNotFound();

        $this->fund($other, Wallet::TillCash, 999999);
        $this->actingAs($this->manager)->get('/cashbox')->assertOk()->assertDontSee('999 999');
    }

    public function test_cashbox_web_flow(): void
    {
        $this->fund($this->branch, Wallet::TillCash, 500000);

        $this->actingAs($this->manager)->post('/cashbox/requests', ['kind' => 'expense', 'method' => 'cash', 'amount' => 120000, 'description' => 'Ijara'])->assertRedirect();
        $req = CashRequest::firstOrFail();
        $this->assertSame(380000, app(WalletService::class)->balance($this->branch->id, Wallet::TillCash));

        // Menejer o'z so'rovini bekor qila oladi, boshqasinikini emas
        $other = $this->user(Role::Manager, $this->branch, ['cashbox.view', 'cashbox.request']);
        $this->actingAs($other)->post("/cashbox/requests/{$req->id}/cancel")->assertForbidden();

        $this->actingAs($this->admin)->post("/cashbox/requests/{$req->id}/approve")->assertRedirect();
        $this->assertSame('approved', $req->fresh()->status);
        $this->actingAs($this->admin)->post("/cashbox/requests/{$req->id}/approve")->assertSessionHasErrors('request');
    }

    public function test_refund_flow_and_confirmation(): void
    {
        $student = $this->student($this->branch);
        $this->actingAs($this->admin)->post("/students/{$student->id}/payments", ['cash' => 300000]);
        $this->actingAs($this->admin)->post("/students/{$student->id}/refund", ['method' => 'cash', 'amount' => 100000, 'description' => 'Ketdi'])->assertRedirect();

        $refund = Payment::where('type', 'refund')->firstOrFail();
        $this->actingAs($this->admin)->get('/cashbox')->assertOk()->assertSee('Tasdiqlanmagan qaytarishlar');
        $this->actingAs($this->admin)->post("/cashbox/refunds/{$refund->id}/confirm")->assertRedirect();
        $this->assertNotNull($refund->fresh()->refund_confirmed_at);
    }

    public function test_finance_and_payroll_pages_and_actions(): void
    {
        $this->fund($this->branch, Wallet::TreasuryCash, 800000);
        $this->actingAs($this->admin)->post('/finance/charity', ['percent' => 7.5])->assertRedirect();
        $this->assertEquals(7.5, $this->branch->fresh()->charity_percent);

        // Xarajat va chiqim endi ikki bosqichli: 1) tekshirish sahifasi, 2) tasdiqlash
        $this->confirm($this->actingAs($this->admin)->post('/finance/expense', ['method' => 'cash', 'amount' => 100000, 'description' => 'Ijara']));
        $this->actingAs($this->admin)->post('/finance/withdraw', ['source' => 'cash', 'amount' => 900000, 'description' => 'Ko\'p'])->assertSessionHasErrors('amount');

        $this->confirm($this->actingAs($this->admin)->post("/payroll/{$this->cat['teacher']->id}", ['method' => 'cash', 'amount' => 200000, 'description' => 'Avans']));
        $this->confirm($this->actingAs($this->admin)->post("/payroll/{$this->manager->id}", ['method' => 'cash', 'amount' => 100000]));
        $this->assertSame(400000, app(WalletService::class)->balance($this->branch->id, Wallet::TreasuryCash));

        foreach (['/finance', '/payroll', '/payroll?tab=staff', "/payroll/{$this->cat['teacher']->id}", "/payroll/{$this->manager->id}", '/payments', '/cashbox', '/'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_student_page_shows_money_panel_and_discount_flow(): void
    {
        $this->actingAs($this->admin);
        $this->cat['plan']->update(['max_discount' => 100000]);
        $group = app(GroupService::class)->create($this->groupPayload($this->cat), $this->admin);
        $student = $this->student($this->branch);
        app(EnrollmentService::class)->enroll($group, $student, null, $this->admin);

        $this->get("/students/{$student->id}")->assertOk()->assertSee("To'lov qabul qilish")->assertSee('Chegirma');

        $this->post("/students/{$student->id}/discount", ['group_id' => $group->id, 'amount' => 70000, 'description' => 'Do\'st'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(-500000 + 70000, $student->fresh()->balance);
        $this->post("/students/{$student->id}/discount", ['group_id' => $group->id, 'amount' => 10000, 'description' => 'Yana'])->assertSessionHasErrors('discount');

        $this->get('/students')->assertOk();
    }

    public function test_settings_campaigns_page_and_dashboard_money(): void
    {
        $this->actingAs($this->admin)->post('/settings/campaigns', ['name' => 'Bayram', 'amount' => 400000, 'bonus' => 50000, 'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/settings/campaigns', ['name' => 'Xato', 'amount' => 1, 'bonus' => 1, 'starts_on' => '2026-09-10', 'ends_on' => '2026-09-01'])->assertSessionHasErrors('ends_on');
        $this->actingAs($this->admin)->get('/settings/campaigns')->assertOk()->assertSee('Bayram');
        $this->actingAs($this->admin)->get('/')->assertOk()->assertSee('Bugungi tushum');
    }

    public function test_student_api_balance(): void
    {
        $student = $this->student($this->branch);
        $this->actingAs($this->admin)->post("/students/{$student->id}/payments", ['cash' => 120000]);
        $token = $student->createToken('t')->plainTextToken;

        $this->api($token)->getJson('/api/v1/me/balance')->assertOk()
            ->assertJsonPath('data.balance', 120000)->assertJsonPath('data.debt', 0)->assertJsonCount(1, 'data.transactions');

        $staff = $this->manager->createToken('m')->plainTextToken;
        $this->api($staff)->getJson('/api/v1/me/balance')->assertForbidden();
    }

    /** Birinchi bosqich «Tekshiring» sahifasiga yo'naltirishi va tasdiqlagandan keyin bajarilishini tekshiradi. */
    private function confirm($response): void
    {
        $response->assertRedirect();
        $url = $response->headers->get('Location');
        $this->assertStringContainsString('/confirm/', $url);
        $this->get($url)->assertOk()->assertSee('Tekshiring');
        $this->post($url)->assertRedirect()->assertSessionHas('success');
    }
}
