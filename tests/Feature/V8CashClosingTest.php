<?php

namespace Tests\Feature;

use App\Enums\PayMethod;
use App\Enums\Role;
use App\Enums\Wallet;
use App\Models\AuditLog;
use App\Models\CashClosing;
use App\Models\CashRequest;
use App\Models\ExpenseCategory;
use App\Services\CashboxService;
use App\Services\ReportService;
use App\Services\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v8 B3: kassa smenasini yopish (kutilgan/haqiqiy naqt) va kassa xarajatlari uchun turkumlar. */
class V8CashClosingTest extends TestCase
{
    use RefreshDatabase;

    private $branch;
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = $this->branch();
        $this->admin = $this->user(Role::Admin, $this->branch, [
            'cashbox.view', 'cashbox.request', 'cashbox.approve', 'cashbox.close', 'settings.branch',
        ]);
    }

    public function test_admin_closes_shift_with_no_difference(): void
    {
        $this->fund($this->branch, Wallet::TillCash, 300000);

        $this->actingAs($this->admin)->post('/cashbox/close', ['actual_cash' => 300000])
            ->assertRedirect()->assertSessionHas('success');

        $closing = CashClosing::firstOrFail();
        $this->assertSame(300000, $closing->expected_cash);
        $this->assertSame(300000, $closing->actual_cash);
        $this->assertSame(0, $closing->difference);
        $this->assertSame($this->admin->id, $closing->closed_by);

        // Kassa balansi o'zgarmagan - bu faqat solishtiruv yozuvi.
        $this->assertSame(300000, app(WalletService::class)->balance($this->branch->id, Wallet::TillCash));

        $log = AuditLog::where('action', 'cashbox.closed')->firstOrFail();
        $this->assertStringContainsString('farqsiz', $log->description);

        // Kassa sahifasi smenalar tarixi bilan xatosiz ochilishi kerak.
        $this->actingAs($this->admin)->get('/cashbox')->assertOk()->assertSee('Kassa smenasi');
    }

    public function test_shortage_and_surplus_are_recorded_correctly(): void
    {
        $this->fund($this->branch, Wallet::TillCash, 500000);

        // Kam (haqiqiy < kutilgan)
        $this->actingAs($this->admin)->post('/cashbox/close', ['actual_cash' => 480000])->assertRedirect();
        $short = CashClosing::firstOrFail();
        $this->assertSame(-20000, $short->difference);

        // Yana bir smena: kutilgan hamon 500000 (yopish kassa balansiga tegmaydi) - endi ortiqcha.
        $this->actingAs($this->admin)->post('/cashbox/close', ['actual_cash' => 510000])->assertRedirect();
        $surplus = CashClosing::orderByDesc('id')->firstOrFail();
        $this->assertSame(500000, $surplus->expected_cash); // kassa hali ham 500000 (post orqali o'zgarmagan)
        $this->assertSame(10000, $surplus->difference);

        $this->assertSame(500000, app(WalletService::class)->balance($this->branch->id, Wallet::TillCash));
        $this->assertSame(2, CashClosing::count());
    }

    public function test_negative_actual_cash_is_rejected(): void
    {
        $this->actingAs($this->admin)->post('/cashbox/close', ['actual_cash' => -1])->assertSessionHasErrors('actual_cash');
        $this->assertSame(0, CashClosing::count());
    }

    public function test_permission_is_required(): void
    {
        $manager = $this->user(Role::Manager, $this->branch, ['cashbox.view']);

        $this->actingAs($manager)->get('/cashbox')->assertOk()->assertDontSee('Smenani yopish');
        $this->actingAs($manager)->post('/cashbox/close', ['actual_cash' => 0])->assertForbidden();
        $this->assertSame(0, CashClosing::count());
    }

    public function test_closings_are_branch_scoped(): void
    {
        $other = $this->branch('Boshqa');
        $adminB = $this->user(Role::Admin, $other, ['cashbox.close']);

        $this->actingAs($this->admin)->post('/cashbox/close', ['actual_cash' => 0])->assertRedirect();
        $this->actingAs($adminB)->post('/cashbox/close', ['actual_cash' => 0])->assertRedirect();

        // Test-muhitida oxirgi actingAs (adminB) filialiga filtrlanadi - shu sabab umumiy sonni
        // withoutGlobalScopes bilan tekshiramiz.
        $this->assertSame(2, CashClosing::withoutGlobalScopes()->count());
        $this->assertSame(1, CashClosing::where('branch_id', $this->branch->id)->withoutGlobalScopes()->count());
    }

    public function test_expense_request_can_have_optional_category(): void
    {
        $this->fund($this->branch, Wallet::TillCash, 1000000);
        $this->actingAs($this->admin)->post('/settings/expense-categories', ['name' => 'Ijara'])->assertRedirect();
        $category = ExpenseCategory::where('name', 'Ijara')->firstOrFail();

        $this->actingAs($this->admin)->post('/cashbox/requests', [
            'kind' => CashRequest::EXPENSE, 'method' => 'cash', 'amount' => 200000, 'description' => 'Sentabr ijarasi', 'category_id' => $category->id,
        ])->assertRedirect()->assertSessionHas('success');

        $request = CashRequest::firstOrFail();
        $this->assertSame($category->id, $request->category_id);

        $this->actingAs($this->admin)->get('/cashbox')->assertOk()->assertSee('Ijara');
    }

    public function test_category_is_ignored_for_withdrawal_kind(): void
    {
        $this->fund($this->branch, Wallet::TillCash, 1000000);
        $category = ExpenseCategory::create(['branch_id' => $this->branch->id, 'name' => 'Ijara', 'is_active' => true]);

        $this->actingAs($this->admin)->post('/cashbox/requests', [
            'kind' => CashRequest::WITHDRAWAL, 'method' => 'cash', 'amount' => 200000, 'description' => 'Moliyaga', 'category_id' => $category->id,
        ])->assertRedirect();

        $this->assertNull(CashRequest::firstOrFail()->category_id);
    }

    public function test_category_from_another_branch_is_rejected(): void
    {
        $this->fund($this->branch, Wallet::TillCash, 1000000);
        $other = $this->branch('Boshqa');
        $foreignCategory = ExpenseCategory::create(['branch_id' => $other->id, 'name' => 'Boshqa filial xarajati', 'is_active' => true]);

        $this->actingAs($this->admin)->post('/cashbox/requests', [
            'kind' => CashRequest::EXPENSE, 'method' => 'cash', 'amount' => 100000, 'description' => 'x', 'category_id' => $foreignCategory->id,
        ])->assertSessionHasErrors('category_id');

        $this->assertSame(0, CashRequest::count());
    }

    public function test_inactive_category_is_rejected(): void
    {
        $this->fund($this->branch, Wallet::TillCash, 1000000);
        $inactive = ExpenseCategory::create(['branch_id' => $this->branch->id, 'name' => 'Eski', 'is_active' => false]);

        $this->actingAs($this->admin)->post('/cashbox/requests', [
            'kind' => CashRequest::EXPENSE, 'method' => 'cash', 'amount' => 100000, 'description' => 'x', 'category_id' => $inactive->id,
        ])->assertSessionHasErrors('category_id');
    }

    public function test_cashflow_report_shows_category_breakdown(): void
    {
        $this->fund($this->branch, Wallet::TillCash, 1000000);
        $category = ExpenseCategory::create(['branch_id' => $this->branch->id, 'name' => 'Kommunal', 'is_active' => true]);

        $this->actingAs($this->admin);
        app(CashboxService::class)->request(CashRequest::EXPENSE, PayMethod::Cash, 150000, 'Svet', $this->admin, $category->id);

        $report = app(ReportService::class)->build('cashflow', CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addDay());

        $this->assertContains('Xarajat turi', $report['columns']);
        $categoryColumnIndex = array_search('Xarajat turi', $report['columns'], true);
        $matched = collect($report['rows'])->first(fn ($row) => $row[$categoryColumnIndex] === 'Kommunal');
        $this->assertNotNull($matched, 'Kategoriya nomi hisobot qatorida topilmadi.');

        $this->assertSame(150000, $report['summary']['Xarajat — Kommunal']);
    }

    public function test_closing_does_not_touch_pending_cash_requests(): void
    {
        $this->fund($this->branch, Wallet::TillCash, 1000000);
        $this->actingAs($this->admin);
        app(CashboxService::class)->request(CashRequest::EXPENSE, PayMethod::Cash, 100000, 'x', $this->admin);

        $this->post('/cashbox/close', ['actual_cash' => 900000])->assertRedirect();

        // So'rov hali ham "pending" - smena yopish uni tasdiqlamaydi/bekor qilmaydi.
        $this->assertSame(CashRequest::PENDING, CashRequest::firstOrFail()->status);
    }
}
