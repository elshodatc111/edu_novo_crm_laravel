<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\Wallet;
use App\Models\Payout;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v13: operatorga ish haqi to'lash (avval 404 berardi) va to'lovlar yig'indisi uchun alohida ruxsat. */
class V13PayrollOperatorAndTotalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_pay_salary_to_operator(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['staff.view', 'staff.pay']);
        $operator = $this->user(Role::Operator, $branch, ['students.view']);
        $this->fund($branch, Wallet::TreasuryCash, 500000);

        $this->actingAs($admin)->get('/payroll?tab=staff')->assertOk()->assertSee($operator->name);
        $this->actingAs($admin)->get("/payroll/{$operator->id}")->assertOk();

        $response = $this->actingAs($admin)->post("/payroll/{$operator->id}", ['method' => 'cash', 'amount' => 120000, 'description' => 'Oylik']);
        $response->assertRedirect();
        $url = $response->headers->get('Location');
        $this->assertStringContainsString('/confirm/', $url);
        $this->get($url)->assertOk()->assertSee('Tekshiring');
        $this->post($url)->assertRedirect()->assertSessionHas('success');

        $this->assertSame(380000, app(WalletService::class)->balance($branch->id, Wallet::TreasuryCash));
        $this->assertSame(1, Payout::where('recipient_id', $operator->id)->count());
    }

    public function test_operator_payout_is_forbidden_without_staff_pay_permission(): void
    {
        $branch = $this->branch();
        $viewer = $this->user(Role::Admin, $branch, ['staff.view']);
        $operator = $this->user(Role::Operator, $branch);
        $this->fund($branch, Wallet::TreasuryCash, 500000);

        $this->actingAs($viewer)->post("/payroll/{$operator->id}", ['method' => 'cash', 'amount' => 1000])->assertForbidden();
    }

    public function test_operator_of_another_branch_is_not_found(): void
    {
        $admin = $this->user(Role::Admin, $this->branch('A'), ['staff.view', 'staff.pay']);
        $foreign = $this->user(Role::Operator, $this->branch('B'));

        $this->actingAs($admin)->get("/payroll/{$foreign->id}")->assertNotFound();
    }

    public function test_payment_totals_cards_need_view_totals_permission(): void
    {
        $branch = $this->branch();
        $without = $this->user(Role::Manager, $branch, ['payments.view']);
        $with = $this->user(Role::Manager, $branch, ['payments.view', 'payments.view_totals']);

        $this->actingAs($without)->get('/payments')->assertOk()->assertDontSee('Chegirma va bonus');
        $this->actingAs($with)->get('/payments')->assertOk()->assertSee('Chegirma va bonus');
    }

    public function test_permission_is_registered_and_not_in_default_templates(): void
    {
        $this->assertTrue(\App\Support\PermissionRegistry::exists('payments.view_totals'));
        $this->assertNotContains('payments.view_totals', \App\Support\PermissionRegistry::defaultsFor(Role::Manager));
        $this->assertNotContains('payments.view_totals', \App\Support\PermissionRegistry::defaultsFor(Role::Operator));
    }
}
