<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\Wallet;
use App\Models\ExpenseCategory;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** v10 (7-band): Moliya → Xarajat formasida ham ixtiyoriy xarajat turini tanlash. */
class V10FinanceExpenseCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_can_be_categorized_and_shown_in_history(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['finance.view', 'finance.manage']);
        $category = ExpenseCategory::create(['branch_id' => $branch->id, 'name' => 'Ijara', 'is_active' => true]);
        $this->fund($branch, Wallet::TreasuryCash, 500000);
        $this->actingAs($admin);

        $url = $this->post('/finance/expense', [
            'method' => 'cash', 'amount' => 100000, 'description' => 'Sentabr ijarasi', 'category_id' => $category->id,
        ])->assertRedirect()->headers->get('Location');

        $this->get($url)->assertOk()->assertSee('Ijara');   // tasdiqlash sahifasida turkum ko'rinadi
        $this->post($url)->assertRedirect(route('finance.index'));

        $this->assertSame(400000, app(WalletService::class)->balance($branch->id, Wallet::TreasuryCash));

        $tx = WalletTransaction::where('branch_id', $branch->id)->where('type', 'expense')->first();
        $this->assertSame($category->id, $tx->category_id);
        $this->assertSame('Ijara', $tx->category->name);

        $this->get('/finance')->assertOk()->assertSee('Ijara');
    }

    public function test_expense_without_category_still_works(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['finance.view', 'finance.manage']);
        $this->fund($branch, Wallet::TreasuryCash, 200000);
        $this->actingAs($admin);

        $url = $this->post('/finance/expense', ['method' => 'cash', 'amount' => 50000, 'description' => 'Boshqa'])
            ->assertRedirect()->headers->get('Location');
        $this->post($url)->assertRedirect(route('finance.index'));

        $tx = WalletTransaction::where('branch_id', $branch->id)->where('type', 'expense')->first();
        $this->assertNull($tx->category_id);
    }

    public function test_category_from_another_branch_is_rejected(): void
    {
        $branchA = $this->branch('A');
        $branchB = $this->branch('B');
        $admin = $this->user(Role::Admin, $branchA, ['finance.view', 'finance.manage']);
        $foreignCategory = ExpenseCategory::create(['branch_id' => $branchB->id, 'name' => 'Boshqa filial turi', 'is_active' => true]);
        $this->fund($branchA, Wallet::TreasuryCash, 500000);

        $this->actingAs($admin)->post('/finance/expense', [
            'method' => 'cash', 'amount' => 50000, 'description' => 'x', 'category_id' => $foreignCategory->id,
        ])->assertSessionHasErrors('category_id');
    }
}
