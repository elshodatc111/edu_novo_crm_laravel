<?php

namespace Tests\Feature;

use App\Enums\PayMethod;
use App\Enums\Role;
use App\Enums\Wallet;
use App\Models\BalanceTransaction;
use App\Models\Branch;
use App\Models\CashRequest;
use App\Models\DiscountCampaign;
use App\Models\Group;
use App\Models\Payment;
use App\Services\CashboxService;
use App\Services\EnrollmentService;
use App\Services\FinanceService;
use App\Services\GroupService;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private $admin;
    private array $cat;
    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00');

        $this->branch = $this->branch();
        $this->admin = $this->user(Role::Admin, $this->branch, ['groups.create', 'groups.members', 'students.view', 'payments.view', 'payments.create', 'payments.discount', 'payments.refund', 'cashbox.view', 'cashbox.request', 'cashbox.approve', 'finance.view', 'finance.manage', 'teachers.view', 'teachers.pay', 'staff.view', 'staff.pay']);
        $this->actingAs($this->admin);
        $this->cat = $this->catalog($this->branch);
        $this->cat['plan']->update(['max_discount' => 100000]);
        $this->group = app(GroupService::class)->create($this->groupPayload($this->cat, ['teacher_rate' => 100000, 'teacher_bonus_rate' => 20000]), $this->admin);
    }

    private function till(Wallet $w = Wallet::TillCash): int
    {
        return app(WalletService::class)->balance($this->branch->id, $w);
    }

    // ---- Hamyon ----

    public function test_wallet_rejects_overdraft_and_logs_transactions(): void
    {
        $wallets = app(WalletService::class);
        $wallets->post($this->branch->id, Wallet::TillCash, 100000, 'payment_in');

        try {
            $wallets->post($this->branch->id, Wallet::TillCash, -150000, 'cash_request');
            $this->fail('Manfiy qoldiqqa ruxsat berildi');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $this->assertSame(100000, $this->till());
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    // ---- To'lovlar ----

    public function test_payment_split_increases_balance_and_till(): void
    {
        $student = $this->student($this->branch);

        app(PaymentService::class)->receive($student, ['cash' => 300000, 'card' => 200000], null, 'Oylik', $this->admin);

        $this->assertSame(500000, $student->fresh()->balance);
        $this->assertSame(300000, $this->till());
        $this->assertSame(200000, $this->till(Wallet::TillCard));
        $this->assertSame(2, Payment::where('type', 'payment')->count());
    }

    public function test_early_discount_after_payment_for_group(): void
    {
        $student = $this->student($this->branch);
        app(EnrollmentService::class)->enroll($this->group, $student, null, $this->admin);   // -500 000

        // Yetarli emas (450 000 dan kam)
        app(PaymentService::class)->receive($student, ['cash' => 400000], $this->group, null, $this->admin);
        $this->assertSame(0, Payment::where('type', 'discount')->count());

        // To'liq (450 000 dan ko'p) -> chegirma 50 000
        app(PaymentService::class)->receive($student, ['cash' => 100000], $this->group, null, $this->admin);
        $this->assertSame(1, Payment::where('type', 'discount')->count());
        $this->assertSame(-500000 + 400000 + 100000 + 50000, $student->fresh()->balance);

        // Ikkinchi marta chegirma berilmaydi
        app(PaymentService::class)->receive($student, ['cash' => 500000], $this->group, null, $this->admin);
        $this->assertSame(1, Payment::where('type', 'discount')->count());
    }

    public function test_no_early_discount_after_grace_period(): void
    {
        $student = $this->student($this->branch);
        app(EnrollmentService::class)->enroll($this->group, $student, null, $this->admin);

        Carbon::setTestNow('2026-09-26 10:00:00');
        app(PaymentService::class)->receive($student, ['cash' => 500000], $this->group, null, $this->admin);

        $this->assertSame(0, Payment::where('type', 'discount')->count());
    }

    public function test_campaigns_are_applied_automatically_best_first_once_each(): void
    {
        $student = $this->student($this->branch);
        $mk = fn ($name, $min, $bonus, $from = '2026-09-01', $to = '2026-09-30') => DiscountCampaign::create(['branch_id' => $this->branch->id, 'name' => $name, 'amount' => $min, 'bonus' => $bonus, 'starts_on' => $from, 'ends_on' => $to]);
        $mk('Kichik', 400000, 40000);
        $mk('Katta', 400000, 60000);
        $mk('Juda katta', 900000, 500000);
        $mk('Eskirgan', 1, 999999, '2026-01-01', '2026-01-31');
        $mk('Kelajak', 1, 999999, '2026-10-05', '2026-10-31');
        $service = app(PaymentService::class);

        // Kam summa - aksiya yo'q
        $service->receive($student, ['cash' => 300000], null, null, $this->admin);
        $this->assertSame(300000, $student->fresh()->balance);

        // 400 000 - mos aksiyalardan eng kattasi (60 000)
        $service->receive($student, ['cash' => 400000], null, null, $this->admin);
        $this->assertSame(300000 + 400000 + 60000, $student->fresh()->balance);

        // Yana 400 000 - "Katta" ishlatilgan, "Kichik" beriladi (har aksiya bir martadan)
        $service->receive($student, ['cash' => 400000], null, null, $this->admin);
        $this->assertSame(760000 + 400000 + 40000, $student->fresh()->balance);

        // Yana 400 000 - endi mos aksiya qolmadi
        $before = $student->fresh()->balance;
        $service->receive($student, ['cash' => 400000], null, null, $this->admin);
        $this->assertSame($before + 400000, $student->fresh()->balance);

        // 900 000 - "Juda katta"
        $service->receive($student, ['cash' => 900000], null, null, $this->admin);
        $this->assertSame(1, Payment::where('student_id', $student->id)->where('type', 'campaign_bonus')->where('amount', 500000)->count());
        $this->assertSame(3, Payment::where('student_id', $student->id)->where('type', 'campaign_bonus')->count());
    }

    public function test_manual_discount_limits(): void
    {
        $student = $this->student($this->branch);
        $service = app(PaymentService::class);
        app(EnrollmentService::class)->enroll($this->group, $student, null, $this->admin);

        try {
            $service->manualDiscount($student, $this->group, 150000, 'Ko\'p', $this->admin);
            $this->fail('Chegaradan oshgan chegirma berildi');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('discount', $e->errors());
        }

        $service->manualDiscount($student, $this->group, 80000, 'Do\'st', $this->admin);
        $this->assertSame(-500000 + 80000, $student->fresh()->balance);

        $this->expectException(ValidationException::class);
        $service->manualDiscount($student, $this->group, 10000, 'Yana', $this->admin);
    }

    public function test_manual_discount_requires_group_membership(): void
    {
        $student = $this->student($this->branch);

        $this->expectException(ValidationException::class);
        app(PaymentService::class)->manualDiscount($student, $this->group, 50000, 'Sabab', $this->admin);
    }

    public function test_refund_limited_by_balance_and_till(): void
    {
        $student = $this->student($this->branch);
        $service = app(PaymentService::class);
        $service->receive($student, ['cash' => 300000], null, null, $this->admin);

        try {
            $service->refund($student, PayMethod::Cash, 400000, 'Ko\'p', $this->admin);
            $this->fail('Balansdan ko\'p qaytarildi');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        // Plastik kassada pul yo'q
        try {
            $service->refund($student, PayMethod::Card, 100000, 'Sabab', $this->admin);
            $this->fail('Bo\'sh kassadan qaytarildi');
        } catch (ValidationException $e) {
            $this->assertSame(300000, $student->fresh()->balance);
        }

        $refund = $service->refund($student, PayMethod::Cash, 100000, 'Sabab', $this->admin);
        $this->assertSame(200000, $student->fresh()->balance);
        $this->assertSame(200000, $this->till());
        $this->assertNull($refund->refund_confirmed_at);

        $service->confirmRefund($refund, $this->admin);
        $this->assertNotNull($refund->fresh()->refund_confirmed_at);
        $this->expectException(ValidationException::class);
        $service->confirmRefund($refund->fresh(), $this->admin);
    }

    // ---- Kassa ----

    public function test_cash_request_flow_expense_and_withdrawal_with_charity(): void
    {
        $this->fund($this->branch, Wallet::TillCash, 1000000);
        $this->branch->update(['charity_percent' => 5]);
        $cashbox = app(CashboxService::class);

        $expense = $cashbox->request(CashRequest::EXPENSE, PayMethod::Cash, 100000, 'Qog\'oz', $this->admin);
        $this->assertSame(900000, $this->till());
        $cashbox->approve($expense, $this->admin);
        $this->assertSame(900000, $this->till());
        $this->assertSame(0, $this->till(Wallet::TreasuryCash));

        $withdrawal = $cashbox->request(CashRequest::WITHDRAWAL, PayMethod::Cash, 200000, 'Moliyaga', $this->admin);
        $this->assertSame(700000, $this->till());
        $cashbox->approve($withdrawal, $this->admin);
        $this->assertSame(190000, $this->till(Wallet::TreasuryCash));
        $this->assertSame(10000, $this->till(Wallet::TreasuryCharityCash));
        $this->assertSame(0, $this->till(Wallet::TreasuryCharityCard));

        // Plastik chiqim: ehson ham plastikdan ajratiladi (plastik ehson balansiga)
        $this->fund($this->branch, Wallet::TillCard, 100000);
        $card = $cashbox->request(CashRequest::WITHDRAWAL, PayMethod::Card, 100000, 'Plastik', $this->admin);
        $cashbox->approve($card, $this->admin);
        $this->assertSame(95000, $this->till(Wallet::TreasuryCard));
        $this->assertSame(5000, $this->till(Wallet::TreasuryCharityCard));
        $this->assertSame(10000, $this->till(Wallet::TreasuryCharityCash));
        $this->assertSame(0, $this->till(Wallet::TreasuryCharity));
    }

    public function test_cancel_returns_money_and_request_cannot_be_decided_twice(): void
    {
        $this->fund($this->branch, Wallet::TillCash, 500000);
        $cashbox = app(CashboxService::class);

        $req = $cashbox->request(CashRequest::EXPENSE, PayMethod::Cash, 300000, 'Xarajat', $this->admin);
        $this->assertSame(200000, $this->till());

        $cashbox->cancel($req, $this->admin);
        $this->assertSame(500000, $this->till());

        $this->expectException(ValidationException::class);
        $cashbox->approve($req->fresh(), $this->admin);
    }

    public function test_cash_request_cannot_exceed_till(): void
    {
        $this->fund($this->branch, Wallet::TillCash, 50000);

        $this->expectException(ValidationException::class);
        app(CashboxService::class)->request(CashRequest::EXPENSE, PayMethod::Cash, 60000, 'Ko\'p', $this->admin);
    }

    // ---- Moliya va ish haqi ----

    public function test_finance_withdraw_and_expense_respect_balances(): void
    {
        $this->fund($this->branch, Wallet::TreasuryCash, 300000);
        $finance = app(FinanceService::class);

        $finance->expense(PayMethod::Cash, 100000, 'Ijara', $this->admin);
        $finance->withdraw('cash', 150000, 'Egasi', $this->admin);
        $this->assertSame(50000, $this->till(Wallet::TreasuryCash));

        $this->expectException(ValidationException::class);
        $finance->withdraw('cash', 60000, 'Ko\'p', $this->admin);
    }

    public function test_teacher_accrual_bonus_attendance_and_payout(): void
    {
        $service = app(EnrollmentService::class);
        $a = $this->student($this->branch);
        $b = $this->student($this->branch);
        $service->enroll($this->group, $a, null, $this->admin);
        $service->enroll($this->group, $b, null, $this->admin);

        // a keyingi guruhda ham faol -> bonusli
        $next = app(GroupService::class)->create($this->groupPayload($this->cat, ['name' => 'a2', 'starts_on' => '2026-10-05']), $this->admin);
        app(PaymentService::class)->receive($a, ['cash' => 1000000], null, null, $this->admin);   // qarzni yopish
        $service->enroll($next, $a, null, $this->admin);

        // 1 ta dars o'tkazilgan (21.09 - dushanba)
        app(\App\Services\AttendanceService::class)->takeToday($this->group, [$a->id, $b->id], $this->cat['teacher']);

        $row = collect(app(\App\Services\PayrollService::class)->teacherAccruals($this->cat['teacher']))->firstWhere('group.id', $this->group->id);

        $this->assertSame(2, $row['students']);
        $this->assertSame(1, $row['bonus']);
        $this->assertSame(2 * 100000 + 1 * 20000, $row['accrued']);            // 220 000
        $this->assertSame(1, $row['held']);
        $this->assertSame((int) round(220000 / 4), $row['accrued_by_attendance']); // 4 ta dars
        $this->assertSame(0, $row['paid']);

        $this->fund($this->branch, Wallet::TreasuryCash, 1000000);
        app(\App\Services\PayrollService::class)->payTeacher($this->cat['teacher'], $this->group, PayMethod::Cash, 30000, 'Avans', $this->admin);

        $row = collect(app(\App\Services\PayrollService::class)->teacherAccruals($this->cat['teacher']))->firstWhere('group.id', $this->group->id);
        $this->assertSame(30000, $row['paid']);
        $this->assertSame(55000 - 30000, $row['remaining']);
        $this->assertSame(970000, $this->till(Wallet::TreasuryCash));
    }

    public function test_payout_validations(): void
    {
        $this->fund($this->branch, Wallet::TreasuryCash, 100000);
        $payroll = app(\App\Services\PayrollService::class);
        $other = $this->user(Role::Teacher, $this->branch);

        try {
            $payroll->payTeacher($other, $this->group, PayMethod::Cash, 1000, null, $this->admin);   // guruh boshqa o'qituvchiniki
            $this->fail('Boshqa o\'qituvchi guruhiga to\'landi');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('group_id', $e->errors());
        }

        try {
            $payroll->payStaff($other, PayMethod::Cash, 1000, null, $this->admin);                   // o'qituvchi hodim emas
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('recipient', $e->errors());
        }

        $manager = $this->user(Role::Manager, $this->branch);
        $this->expectException(ValidationException::class);
        $payroll->payStaff($manager, PayMethod::Cash, 500000, null, $this->admin);   // moliyada mablag' yetarli emas
    }
}
