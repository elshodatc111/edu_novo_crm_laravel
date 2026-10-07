<?php

namespace Tests\Feature;

use App\Enums\PayMethod;
use App\Enums\Role;
use App\Enums\Wallet;
use App\Models\CashRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** v13: kassada «Xarajat» (cashbox.request) va «Chiqim» (cashbox.withdraw) ruxsatlari ajratildi. */
class V13CashboxSplitPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $kind): array
    {
        return ['kind' => $kind, 'method' => 'cash', 'amount' => 10000, 'description' => 'Sinov'];
    }

    public function test_expense_only_user_cannot_create_withdrawal(): void
    {
        $b = $this->branch();
        $u = $this->user(Role::Manager, $b, ['cashbox.view', 'cashbox.request']);
        $this->fund($b, Wallet::TillCash, 500000);

        $this->actingAs($u)->post('/cashbox/requests', $this->payload('withdrawal'))->assertForbidden();
        $this->assertSame(0, CashRequest::count());

        $this->actingAs($u)->post('/cashbox/requests', $this->payload('expense'))->assertRedirect();
        $this->assertSame(1, CashRequest::count());
    }

    public function test_withdraw_only_user_cannot_create_expense(): void
    {
        $b = $this->branch();
        $u = $this->user(Role::Manager, $b, ['cashbox.view', 'cashbox.withdraw']);
        $this->fund($b, Wallet::TillCash, 500000);

        $this->actingAs($u)->post('/cashbox/requests', $this->payload('expense'))->assertForbidden();
        $this->actingAs($u)->post('/cashbox/requests', $this->payload('withdrawal'))->assertRedirect();

        $this->assertSame(['withdrawal'], CashRequest::pluck('kind')->all());
    }

    public function test_user_without_either_permission_is_forbidden(): void
    {
        $b = $this->branch();
        $u = $this->user(Role::Manager, $b, ['cashbox.view']);

        $this->actingAs($u)->post('/cashbox/requests', $this->payload('expense'))->assertForbidden();
        $this->actingAs($u)->post('/cashbox/requests', $this->payload('withdrawal'))->assertForbidden();
    }

    public function test_form_shows_only_allowed_kinds(): void
    {
        $b = $this->branch();
        $both = $this->user(Role::Manager, $b, ['cashbox.view', 'cashbox.request', 'cashbox.withdraw']);
        $expense = $this->user(Role::Manager, $b, ['cashbox.view', 'cashbox.request']);
        $withdraw = $this->user(Role::Manager, $b, ['cashbox.view', 'cashbox.withdraw']);
        $none = $this->user(Role::Manager, $b, ['cashbox.view']);

        $this->actingAs($both)->get('/cashbox')->assertOk()->assertSee('Xarajat (sarflandi)')->assertSee('moliya balansiga');
        $this->actingAs($expense)->get('/cashbox')->assertOk()->assertSee('Xarajat (sarflandi)')->assertDontSee('moliya balansiga');
        $this->actingAs($withdraw)->get('/cashbox')->assertOk()->assertSee('moliya balansiga')->assertDontSee('Xarajat (sarflandi)');
        $this->actingAs($none)->get('/cashbox')->assertOk()->assertDontSee("So'rov yaratish");
    }

    public function test_user_cancels_own_request_only_with_matching_permission(): void
    {
        $b = $this->branch();
        $this->fund($b, Wallet::TillCash, 500000);
        $u = $this->user(Role::Manager, $b, ['cashbox.view', 'cashbox.request', 'cashbox.withdraw']);
        $this->actingAs($u);
        $expense = app(\App\Services\CashboxService::class)->request('expense', PayMethod::Cash, 10000, 'x', $u);
        $withdrawal = app(\App\Services\CashboxService::class)->request('withdrawal', PayMethod::Cash, 10000, 'y', $u);

        // Chiqim huquqi olib tashlansa, o'z chiqim so'rovini bekor qila olmaydi, xarajatni esa bora oladi
        $u->syncPermissions(['cashbox.view', 'cashbox.request']);
        $u = $u->fresh();

        $this->actingAs($u)->post(route('cashbox.cancel', $withdrawal))->assertForbidden();
        $this->actingAs($u)->post(route('cashbox.cancel', $expense))->assertRedirect();
    }

    public function test_api_enforces_kind_permissions(): void
    {
        $b = $this->branch();
        $this->fund($b, Wallet::TillCash, 500000);
        $u = $this->user(Role::Operator, $b, ['cashbox.request']);
        $token = $u->createToken('m')->plainTextToken;

        $this->api($token)->postJson('/api/v1/cashbox/requests', $this->payload('withdrawal'))->assertForbidden();
        $this->api($token)->postJson('/api/v1/cashbox/requests', $this->payload('expense'))->assertCreated();
    }

    public function test_permission_is_registered_with_separate_labels(): void
    {
        $this->assertTrue(\App\Support\PermissionRegistry::exists('cashbox.withdraw'));
        $this->assertTrue(\App\Support\PermissionRegistry::exists('cashbox.request'));
    }

    public function test_migration_grants_withdraw_to_existing_request_holders(): void
    {
        $b = $this->branch();
        $holder = $this->user(Role::Manager, $b, ['cashbox.request']);
        $other = $this->user(Role::Manager, $b, ['cashbox.view']);

        (require base_path('database/migrations/2026_10_08_000004_v13_cashbox_withdraw_permission.php'))->up();

        $this->assertEqualsCanonicalizing(['cashbox.request', 'cashbox.withdraw'], $holder->fresh()->permissionKeys());
        $this->assertSame(['cashbox.view'], $other->fresh()->permissionKeys());
        $this->assertSame(0, DB::table('user_permissions')->where('user_id', $other->id)->where('permission', 'cashbox.withdraw')->count());
    }
}
