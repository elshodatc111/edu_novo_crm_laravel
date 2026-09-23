<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\Wallet;
use App\Models\AuditLog;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v9 (2-band): admin/sAdmin o'z shaxsiy mablag'ini moliyaga (naqt/plastik) kiritishi. */
class V9FinanceDepositTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_deposit_cash_immediately_without_confirmation_step(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['finance.deposit']);

        $response = $this->actingAs($admin)->post('/finance/deposit', [
            'method' => 'cash',
            'amount' => 250000,
            'description' => "Do'kondan qarz",
        ]);

        $response->assertRedirect()->assertSessionHas('success');

        $wallets = app(WalletService::class);
        $this->assertSame(250000, $wallets->balance($branch->id, Wallet::TreasuryCash));

        $tx = WalletTransaction::where('branch_id', $branch->id)->where('type', 'owner_deposit')->first();
        $this->assertNotNull($tx);
        $this->assertSame(250000, $tx->amount);
        $this->assertSame(Wallet::TreasuryCash->value, $tx->wallet);
        $this->assertSame("Do'kondan qarz", $tx->description);

        $this->assertTrue(AuditLog::where('action', 'finance.deposit')->where('branch_id', $branch->id)->exists());
    }

    public function test_deposit_by_card_credits_card_treasury_without_charity_split(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['finance.deposit']);

        $this->actingAs($admin)->post('/finance/deposit', [
            'method' => 'card',
            'amount' => 100000,
            'description' => 'Shaxsiy kartadan',
        ])->assertRedirect();

        $wallets = app(WalletService::class);
        $this->assertSame(100000, $wallets->balance($branch->id, Wallet::TreasuryCard));
        $this->assertSame(0, $wallets->balance($branch->id, Wallet::TreasuryCharityCash));
        $this->assertSame(0, $wallets->balance($branch->id, Wallet::TreasuryCharityCard));
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $branch = $this->branch();
        $manager = $this->user(Role::Manager, $branch, ['finance.view']);

        $this->actingAs($manager)->post('/finance/deposit', [
            'method' => 'cash',
            'amount' => 10000,
            'description' => 'Test',
        ])->assertForbidden();
    }

    public function test_deposit_form_only_visible_with_permission(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['finance.view', 'finance.manage', 'finance.deposit']);
        $limitedAdmin = $this->user(Role::Admin, $branch, ['finance.view'], ['name' => 'Cheklangan Admin']);

        $this->actingAs($admin)->get('/finance')->assertOk()->assertSee("Shaxsiy mablag' kiritish", false);
        $this->actingAs($limitedAdmin)->get('/finance')->assertOk()->assertDontSee("Shaxsiy mablag' kiritish", false);
    }
}
