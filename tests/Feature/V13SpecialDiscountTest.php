<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\BalanceTransaction;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\StatisticsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** v13.2: sAdmin'ning maxsus chegirmasi (guruhga bog'lanmagan, 1 000 000 gacha, parol bilan tasdiqlanadi). */
class V13SpecialDiscountTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $branch = $this->branch();
        $sadmin = $this->user(Role::SAdmin);
        $student = $this->student($branch);

        return [$branch, $sadmin, $student];
    }

    private function as($user, $branch)
    {
        return $this->actingAs($user)->withSession(['current_branch_id' => $branch->id]);
    }

    private function initiate($sadmin, $branch, $student, array $over = [])
    {
        return $this->as($sadmin, $branch)->post("/students/{$student->id}/special-discount", $over + ['special_amount' => '500 000', 'special_description' => "Kam ta'minlangan oila"]);
    }

    public function test_sadmin_gives_special_discount_with_password_and_balance_grows_without_cash_or_sms(): void
    {
        Http::fake();
        [$branch, $sadmin, $student] = $this->ctx();

        $url = $this->initiate($sadmin, $branch, $student)->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString('/confirm/', $url);
        $this->assertSame(0, $student->fresh()->balance);               // 1-bosqichda hech narsa o'zgarmaydi

        $this->as($sadmin, $branch)->get($url)->assertOk()->assertSee('Parolingiz')->assertSee('Maxsus chegirma');
        $this->as($sadmin, $branch)->post($url, ['password' => 'parol12345'])->assertRedirect(route('students.show', $student))->assertSessionHas('success');

        $this->assertSame(500000, $student->fresh()->balance);
        $p = Payment::where('student_id', $student->id)->first();
        $this->assertSame(Payment::DISCOUNT, $p->type);
        $this->assertNull($p->group_id);
        $this->assertNull($p->method);                                   // kassaga tegmaydi
        $this->assertStringContainsString("Maxsus chegirma", $p->description);
        $this->assertDatabaseHas('balance_transactions', ['student_id' => $student->id, 'type' => BalanceTransaction::SPECIAL_DISCOUNT, 'amount' => 500000]);
        $this->assertSame(0, \App\Models\WalletTransaction::count());      // kassada hech qanday harakat yo'q
        $this->assertTrue(AuditLog::where('action', 'payment.special_discount')->exists());
        $this->assertSame(0, \App\Models\NotificationRecipient::count());
    }

    public function test_statistics_count_special_discount_in_discounts(): void
    {
        [$branch, $sadmin, $student] = $this->ctx();
        $this->actingAs($sadmin);
        \App\Support\BranchContext::select($branch->id);

        app(PaymentService::class)->specialDiscount($student, 300000, 'Sabab', $sadmin);

        $o = app(StatisticsService::class)->overview(CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today());
        $this->assertSame(300000, $o['discounts']);
    }

    public function test_wrong_password_does_not_apply_and_token_stays_valid(): void
    {
        [$branch, $sadmin, $student] = $this->ctx();
        $url = $this->initiate($sadmin, $branch, $student)->headers->get('Location');

        $this->as($sadmin, $branch)->post($url, ['password' => 'noto-g-ri'])->assertSessionHasErrors('password');
        $this->as($sadmin, $branch)->post($url, [])->assertSessionHasErrors('password');
        $this->assertSame(0, $student->fresh()->balance);

        $this->as($sadmin, $branch)->post($url, ['password' => 'parol12345'])->assertSessionHas('success');
        $this->assertSame(500000, $student->fresh()->balance);

        // Tasdiq bir marta ishlaydi
        $this->as($sadmin, $branch)->post($url, ['password' => 'parol12345']);
        $this->assertSame(500000, $student->fresh()->balance);
    }

    public function test_amount_is_limited_to_one_million_and_reason_is_required(): void
    {
        [$branch, $sadmin, $student] = $this->ctx();

        $this->initiate($sadmin, $branch, $student, ['special_amount' => '1 000 001'])->assertSessionHasErrors('special_amount');
        $this->initiate($sadmin, $branch, $student, ['special_amount' => '0'])->assertSessionHasErrors('special_amount');
        $this->initiate($sadmin, $branch, $student, ['special_description' => ''])->assertSessionHasErrors('special_description');
        $this->initiate($sadmin, $branch, $student, ['special_amount' => '1 000 000'])->assertRedirect();

        // Servis ham alohida himoyalangan
        $this->actingAs($sadmin);
        \App\Support\BranchContext::select($branch->id);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PaymentService::class)->specialDiscount($student, 1000001, 'Sabab', $sadmin);
    }

    public function test_admin_manager_operator_cannot_use_it_even_with_every_discount_permission(): void
    {
        $branch = $this->branch();
        $student = $this->student($branch);
        $perms = ['payments.view', 'payments.create', 'payments.discount', 'payments.refund', 'payments.reverse', 'students.view'];

        foreach ([Role::Admin, Role::Manager, Role::Operator] as $role) {
            $u = $this->user($role, $branch, $perms);
            $this->actingAs($u)->post("/students/{$student->id}/special-discount", ['special_amount' => 1000, 'special_description' => 'x'])->assertForbidden();
            $this->assertFalse(app(\Illuminate\Contracts\Auth\Access\Gate::class)->forUser($u)->allows('payments.special_discount'));
        }

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(PaymentService::class)->specialDiscount($student, 1000, 'x', $this->user(Role::Admin, $branch, $perms));
    }

    public function test_tab_is_visible_only_to_sadmin(): void
    {
        [$branch, $sadmin, $student] = $this->ctx();
        $admin = $this->user(Role::Admin, $branch, ['students.view', 'payments.create', 'payments.discount', 'payments.refund']);

        $this->as($sadmin, $branch)->get("/students/{$student->id}")->assertOk()->assertSee('Maxsus chegirma');
        $this->actingAs($admin)->get("/students/{$student->id}")->assertOk()->assertDontSee('Maxsus chegirma');
    }

    public function test_special_discount_is_reversible_and_other_branch_student_is_404(): void
    {
        [$branch, $sadmin, $student] = $this->ctx();
        $this->actingAs($sadmin);
        \App\Support\BranchContext::select($branch->id);
        $p = app(PaymentService::class)->specialDiscount($student, 200000, 'Sabab', $sadmin);
        $this->assertSame(200000, $student->fresh()->balance);

        app(PaymentService::class)->reverse($p, 'Xato berilgan', $sadmin);
        $this->assertSame(0, $student->fresh()->balance);

        $other = $this->student($this->branch('Boshqa'));
        $this->as($sadmin, $branch)->post("/students/{$other->id}/special-discount", ['special_amount' => 1000, 'special_description' => 'x'])->assertNotFound();
    }

    public function test_existing_admin_discount_rules_are_unchanged(): void
    {
        [$branch, $sadmin, $student] = $this->ctx();
        $admin = $this->user(Role::Admin, $branch, ['payments.discount', 'students.view']);

        // Admin chegirmasi guruhsiz ishlamaydi (guruh majburiy) - avvalgidek
        $this->actingAs($admin)->post("/students/{$student->id}/discount", ['amount' => 1000, 'description' => 'x'])->assertSessionHasErrors('group_id');
    }
}
