<?php

namespace Tests\Feature;

use App\Enums\PayMethod;
use App\Enums\Role;
use App\Enums\Wallet;
use App\Models\BalanceTransaction;
use App\Models\DiscountCampaign;
use App\Models\Payment;
use App\Services\EarlyDiscountService;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * V8 A2: to'lov/chegirma/aksiya bonusini STORNO qilish (xato yozuvni teskari yozuv bilan bekor qilish)
 * va qaytarishni RAD ETISH (pul qaytarilib, so'ralgan qaytarish bekor qilinadi).
 */
class V8ReversalTest extends TestCase
{
    use RefreshDatabase;

    private $branch;
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00');
        $this->branch = $this->branch('Toshkent');
        $this->admin = $this->user(Role::Admin, $this->branch, [
            'groups.create', 'groups.members', 'groups.view', 'students.view',
            'payments.view', 'payments.create', 'payments.discount', 'payments.refund', 'payments.reverse',
            'cashbox.view', 'cashbox.approve',
        ]);
    }

    private function bal(Wallet $w): int
    {
        return app(WalletService::class)->balance($this->branch->id, $w);
    }

    // ---------------------------------------------------------------- Storno: to'lov

    public function test_payment_can_be_reversed_and_money_is_taken_back(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student($this->branch);
        [$payment] = app(PaymentService::class)->receive($student, ['cash' => 200000], null, null, $this->admin);

        $this->assertSame(200000, $student->fresh()->balance);
        $this->assertSame(200000, $this->bal(Wallet::TillCash));

        $url = $this->post("/payments/{$payment->id}/reverse", ['reason' => "Xato o'quvchiga kiritilgan"])
            ->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString('/confirm/', $url);
        $this->assertSame(200000, $student->fresh()->balance);          // 1-bosqichda hali pul harakatlanmagan

        $this->get($url)->assertOk()->assertSee('Tekshiring')->assertSee('Xato');
        $this->post($url)->assertRedirect(route('students.show', $student))->assertSessionHas('success');

        $this->assertSame(0, $student->fresh()->balance);
        $this->assertSame(0, $this->bal(Wallet::TillCash));

        $payment->refresh();
        $this->assertNotNull($payment->reversed_at);
        $this->assertSame($this->admin->id, $payment->reversed_by);
        $this->assertSame("Xato o'quvchiga kiritilgan", $payment->reverse_reason);
        $this->assertFalse($payment->isReversible());

        $reversal = Payment::where('reversal_of_id', $payment->id)->sole();
        $this->assertSame(Payment::REVERSAL, $reversal->type);
        $this->assertSame(200000, $reversal->amount);
        $this->assertSame(1, BalanceTransaction::where('type', BalanceTransaction::PAYMENT_REVERSAL)->count());
    }

    public function test_reverse_confirmation_executes_only_once(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student($this->branch);
        [$payment] = app(PaymentService::class)->receive($student, ['cash' => 100000], null, null, $this->admin);

        $url = $this->post("/payments/{$payment->id}/reverse", ['reason' => 'Xato'])->headers->get('Location');
        $this->post($url)->assertSessionHas('success');
        $this->assertSame(0, $student->fresh()->balance);

        $this->post($url)->assertRedirect(route('dashboard'))->assertSessionHas('error');
        $this->assertSame(0, $student->fresh()->balance);
    }

    public function test_already_reversed_payment_cannot_be_reversed_again(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student($this->branch);
        [$payment] = app(PaymentService::class)->receive($student, ['cash' => 100000], null, null, $this->admin);

        app(PaymentService::class)->reverse($payment, 'Birinchi storno', $this->admin);

        $this->post("/payments/{$payment->id}/reverse", ['reason' => "Ikkinchi urinish"])->assertSessionHasErrors('payment');
    }

    public function test_reversal_is_blocked_when_till_lacks_funds(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student($this->branch);
        [$payment] = app(PaymentService::class)->receive($student, ['cash' => 200000], null, null, $this->admin);

        // Pul kassadan boshqa joyga (masalan xarajatga) chiqarib yuborilgan
        app(WalletService::class)->post($this->branch->id, Wallet::TillCash, -150000, 'expense');
        $this->assertSame(50000, $this->bal(Wallet::TillCash));

        $url = $this->post("/payments/{$payment->id}/reverse", ['reason' => 'Xato'])->headers->get('Location');
        $this->post($url)->assertSessionHasErrors('amount');

        $payment->refresh();
        $this->assertNull($payment->reversed_at);
        $this->assertSame(50000, $this->bal(Wallet::TillCash));
        $this->assertSame(200000, $student->fresh()->balance);           // balans o'zgarmadi, storno bekor bo'ldi
    }

    public function test_reason_is_required_to_reverse(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student($this->branch);
        [$payment] = app(PaymentService::class)->receive($student, ['cash' => 100000], null, null, $this->admin);

        $this->post("/payments/{$payment->id}/reverse", [])->assertSessionHasErrors('reason');
    }

    public function test_reverse_requires_permission(): void
    {
        $student = $this->student($this->branch);
        [$payment] = app(PaymentService::class)->receive($student, ['cash' => 100000], null, null, $this->admin);

        $manager = $this->user(Role::Manager, $this->branch, ['payments.view', 'payments.create']);
        $this->actingAs($manager)->post("/payments/{$payment->id}/reverse", ['reason' => 'x'])->assertForbidden();

        $noPerm = $this->user(Role::Admin, $this->branch, ['payments.view']);
        $this->actingAs($noPerm)->post("/payments/{$payment->id}/reverse", ['reason' => 'x'])->assertForbidden();

        $this->assertNull($payment->fresh()->reversed_at);
    }

    public function test_reversal_warning_shown_when_related_discount_exists_same_day(): void
    {
        $this->actingAs($this->admin);
        $cat = $this->catalog($this->branch);
        $g = app(GroupService::class)->create($this->groupPayload($cat), $this->admin); // narx 500000, chegirma 50000
        $student = $this->student($this->branch);
        app(EnrollmentService::class)->enroll($g, $student, null, $this->admin);

        [$payment] = app(PaymentService::class)->receive($student, ['cash' => 450000], null, null, $this->admin); // chegirma shu bilan birga beriladi
        $this->assertSame(1, Payment::where('type', Payment::DISCOUNT)->count());

        $url = $this->post("/payments/{$payment->id}/reverse", ['reason' => 'Xato'])->headers->get('Location');
        $this->get($url)->assertOk()->assertSee('aksiya bonusi berilgan');
    }

    public function test_no_reversal_warning_for_unrelated_payment(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student($this->branch);
        [$payment] = app(PaymentService::class)->receive($student, ['cash' => 50000], null, null, $this->admin);

        $url = $this->post("/payments/{$payment->id}/reverse", ['reason' => 'Xato'])->headers->get('Location');
        $this->get($url)->assertOk()->assertDontSee('aksiya bonusi berilgan');
    }

    // ---------------------------------------------------------------- Storno: chegirma va aksiya bonusi

    public function test_reversed_early_discount_can_be_granted_again(): void
    {
        $this->actingAs($this->admin);
        $cat = $this->catalog($this->branch);
        $g = app(GroupService::class)->create($this->groupPayload($cat), $this->admin); // narx 500000, chegirma 50000
        $student = $this->student($this->branch);
        app(EnrollmentService::class)->enroll($g, $student, null, $this->admin);
        $pay = app(PaymentService::class);

        $pay->receive($student, ['cash' => 450000], null, null, $this->admin);
        $discount = Payment::where('type', Payment::DISCOUNT)->where('group_id', $g->id)->sole();
        $this->assertSame(0, $student->fresh()->balance);

        $pay->reverse($discount, "Xato chegirma berilgan", $this->admin);
        $this->assertSame(-50000, $student->fresh()->balance);
        $this->assertSame(0, Payment::where('type', Payment::DISCOUNT)->where('group_id', $g->id)->whereNull('reversed_at')->count());

        // Shart hali bajarilgani uchun (avvalgi to'lovlar reverse qilinmagan), yana bitta kichik to'lov chegirmani qayta beradi
        $pay->receive($student->fresh(), ['cash' => 1000], null, null, $this->admin);
        $this->assertSame(1, Payment::where('type', Payment::DISCOUNT)->where('group_id', $g->id)->whereNull('reversed_at')->count());
        $this->assertSame(1000, $student->fresh()->balance);
    }

    public function test_reversing_discount_does_not_touch_the_payment_that_caused_it(): void
    {
        $this->actingAs($this->admin);
        $cat = $this->catalog($this->branch);
        $g = app(GroupService::class)->create($this->groupPayload($cat), $this->admin);
        $student = $this->student($this->branch);
        app(EnrollmentService::class)->enroll($g, $student, null, $this->admin);
        $pay = app(PaymentService::class);

        [$payment] = $pay->receive($student, ['cash' => 450000], null, null, $this->admin);
        $discount = Payment::where('type', Payment::DISCOUNT)->where('group_id', $g->id)->sole();

        $pay->reverse($discount, 'Chegirma xato edi', $this->admin);

        $this->assertTrue($payment->fresh()->isReversible());        // to'lovning o'zi tegilmagan, hali storno qilish mumkin
        $this->assertNull($payment->fresh()->reversed_at);
    }

    public function test_reversed_campaign_bonus_frees_campaign_for_reuse(): void
    {
        $this->actingAs($this->admin);
        $campaign = DiscountCampaign::create([
            'branch_id' => $this->branch->id, 'name' => 'Sentabr aksiyasi', 'amount' => 100000, 'bonus' => 20000,
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30', 'is_active' => true,
        ]);
        $student = $this->student($this->branch);
        $pay = app(PaymentService::class);

        $pay->receive($student, ['cash' => 100000], null, null, $this->admin);
        $bonus = Payment::where('type', Payment::CAMPAIGN_BONUS)->where('campaign_id', $campaign->id)->sole();
        $this->assertSame(120000, $student->fresh()->balance);

        // Aksiya allaqachon ishlatilgan, qayta berilmaydi
        $pay->receive($student->fresh(), ['cash' => 100000], null, null, $this->admin);
        $this->assertSame(1, Payment::where('type', Payment::CAMPAIGN_BONUS)->count());

        $pay->reverse($bonus, 'Xato bonus berilgan', $this->admin);

        // Endi aksiya yana ishlatilishi mumkin
        $pay->receive($student->fresh(), ['cash' => 100000], null, null, $this->admin);
        $this->assertSame(2, Payment::where('type', Payment::CAMPAIGN_BONUS)->count());
    }

    public function test_discount_and_campaign_bonus_reversal_does_not_move_till(): void
    {
        $this->actingAs($this->admin);
        $cat = $this->catalog($this->branch);
        $g = app(GroupService::class)->create($this->groupPayload($cat), $this->admin);
        $student = $this->student($this->branch);
        app(EnrollmentService::class)->enroll($g, $student, null, $this->admin);
        $pay = app(PaymentService::class);

        $pay->receive($student, ['cash' => 450000], null, null, $this->admin);
        $tillAfterPayment = $this->bal(Wallet::TillCash);
        $discount = Payment::where('type', Payment::DISCOUNT)->sole();

        $pay->reverse($discount, 'Xato', $this->admin);

        $this->assertSame($tillAfterPayment, $this->bal(Wallet::TillCash)); // kassa chegirma storno qilinganda o'zgarmaydi
    }

    public function test_refund_and_reversal_type_cannot_be_reversed_via_storno(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student($this->branch);
        $pay = app(PaymentService::class);
        [$payment] = $pay->receive($student, ['cash' => 200000], null, null, $this->admin);
        $refund = $pay->refund($student->fresh(), PayMethod::Cash, 50000, 'Sabab', $this->admin);

        $this->assertFalse($refund->isReversible());

        $this->expectException(ValidationException::class);
        $pay->reverse($refund, 'Storno urinishi', $this->admin);
    }

    // ---------------------------------------------------------------- Oldindan to'lov chegirmasi hisob-kitobi

    public function test_paid_since_window_opened_excludes_reversed_payments_and_rejected_refunds(): void
    {
        $this->actingAs($this->admin);
        $cat = $this->catalog($this->branch);
        $g = app(GroupService::class)->create($this->groupPayload($cat), $this->admin);
        $student = $this->student($this->branch);
        $pay = app(PaymentService::class);
        $early = app(EarlyDiscountService::class);

        [$p1] = $pay->receive($student, ['cash' => 100000], null, null, $this->admin);
        $this->assertSame(100000, $early->paidSinceWindowOpened($student->fresh(), $g));

        $pay->reverse($p1, 'Xato', $this->admin);
        $this->assertSame(0, $early->paidSinceWindowOpened($student->fresh(), $g));

        $pay->receive($student->fresh(), ['cash' => 200000], null, null, $this->admin);
        $refund = $pay->refund($student->fresh(), PayMethod::Cash, 50000, 'Qaytarish', $this->admin);
        $this->assertSame(150000, $early->paidSinceWindowOpened($student->fresh(), $g));

        $pay->rejectRefund($refund, "Xato so'ralgan", $this->admin);
        $this->assertSame(200000, $early->paidSinceWindowOpened($student->fresh(), $g));
    }

    // ---------------------------------------------------------------- Qaytarishni rad etish

    public function test_refund_rejection_returns_money_to_till_and_balance(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student($this->branch);
        app(PaymentService::class)->receive($student, ['cash' => 300000], null, null, $this->admin);

        $refund = app(PaymentService::class)->refund($student->fresh(), PayMethod::Cash, 100000, 'Talaba noroziligi', $this->admin);
        $this->assertSame(200000, $student->fresh()->balance);
        $this->assertSame(200000, $this->bal(Wallet::TillCash));

        $this->post("/cashbox/refunds/{$refund->id}/reject", ['reason' => "Talaba fikridan qaytdi"])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(300000, $student->fresh()->balance);
        $this->assertSame(300000, $this->bal(Wallet::TillCash));

        $refund->refresh();
        $this->assertNotNull($refund->refund_rejected_at);
        $this->assertSame($this->admin->id, $refund->refund_rejected_by);
        $this->assertSame("Talaba fikridan qaytdi", $refund->refund_reject_reason);
        $this->assertSame(1, BalanceTransaction::where('type', BalanceTransaction::PAYMENT_REFUND_REJECTED)->count());
    }

    public function test_confirmed_refund_cannot_be_rejected(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student($this->branch);
        app(PaymentService::class)->receive($student, ['cash' => 300000], null, null, $this->admin);
        $refund = app(PaymentService::class)->refund($student->fresh(), PayMethod::Cash, 100000, 'Sabab', $this->admin);

        $this->post("/cashbox/refunds/{$refund->id}/confirm")->assertSessionHas('success');
        $this->post("/cashbox/refunds/{$refund->id}/reject", ['reason' => 'Kech qoldi'])->assertSessionHasErrors('payment');

        $refund->refresh();
        $this->assertNull($refund->refund_rejected_at);
        $this->assertNotNull($refund->refund_confirmed_at);
        $this->assertSame(200000, $student->fresh()->balance);   // pul qaytarilmagan
    }

    public function test_rejected_refund_cannot_be_confirmed(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student($this->branch);
        app(PaymentService::class)->receive($student, ['cash' => 300000], null, null, $this->admin);
        $refund = app(PaymentService::class)->refund($student->fresh(), PayMethod::Cash, 100000, 'Sabab', $this->admin);

        $this->post("/cashbox/refunds/{$refund->id}/reject", ['reason' => "Xato so'ralgan"])->assertSessionHas('success');
        $this->post("/cashbox/refunds/{$refund->id}/confirm")->assertSessionHasErrors('payment');

        $this->assertSame(300000, $student->fresh()->balance);
    }

    public function test_refund_rejection_reason_is_required(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student($this->branch);
        app(PaymentService::class)->receive($student, ['cash' => 300000], null, null, $this->admin);
        $refund = app(PaymentService::class)->refund($student->fresh(), PayMethod::Cash, 100000, 'Sabab', $this->admin);

        $this->post("/cashbox/refunds/{$refund->id}/reject", [])->assertSessionHasErrors('reason');
        $this->assertNull($refund->fresh()->refund_rejected_at);
    }

    public function test_refund_rejection_requires_cashbox_approve_permission(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student($this->branch);
        app(PaymentService::class)->receive($student, ['cash' => 300000], null, null, $this->admin);
        $refund = app(PaymentService::class)->refund($student->fresh(), PayMethod::Cash, 100000, 'Sabab', $this->admin);

        $noPerm = $this->user(Role::Admin, $this->branch, ['cashbox.view']);
        $this->actingAs($noPerm)->post("/cashbox/refunds/{$refund->id}/reject", ['reason' => 'x'])->assertForbidden();
    }

    // ---------------------------------------------------------------- Talaba sahifasidagi boshqaruv

    public function test_student_page_shows_storno_control_only_with_permission(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student($this->branch);
        app(PaymentService::class)->receive($student, ['cash' => 100000], null, null, $this->admin);

        $this->get("/students/{$student->id}")->assertOk()->assertSee('Storno');

        $manager = $this->user(Role::Manager, $this->branch, ['students.view', 'payments.view']);
        $this->actingAs($manager)->get("/students/{$student->id}")->assertOk()->assertDontSee('Storno');
    }

    public function test_student_page_shows_reversed_badge_after_storno(): void
    {
        $this->actingAs($this->admin);
        $student = $this->student($this->branch);
        [$payment] = app(PaymentService::class)->receive($student, ['cash' => 100000], null, null, $this->admin);
        app(PaymentService::class)->reverse($payment, 'Xato', $this->admin);

        $this->get("/students/{$student->id}")->assertOk()->assertSee('Stornolangan');
    }
}
