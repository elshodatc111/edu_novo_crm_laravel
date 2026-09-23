<?php

namespace Tests\Feature;

use App\Enums\PayMethod;
use App\Enums\Role;
use App\Enums\Wallet;
use App\Models\CashRequest;
use App\Models\Lead;
use App\Services\CashboxService;
use App\Services\EnrollmentService;
use App\Services\FinanceService;
use App\Services\GroupService;
use App\Services\PaymentService;
use App\Services\PayrollService;
use App\Services\StatisticsService;
use App\Support\Viz;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** V7: statistika dashboardlari, varonka voronkasi, davomad grafiklari (grafik + jadval ikkilamchisi). */
class V7DashboardsTest extends TestCase
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
        $this->admin = $this->user(Role::Admin, $this->branch, ['groups.create', 'groups.members', 'students.view', 'payments.view', 'payments.create', 'payments.refund', 'cashbox.request', 'cashbox.approve', 'finance.view', 'finance.manage', 'teachers.view', 'teachers.pay', 'statistics.view', 'attendance.stats', 'leads.view']);
        $this->actingAs($this->admin);
        $this->cat = $this->catalog($this->branch);
    }

    private function scenario(): void
    {
        $group = app(GroupService::class)->create($this->groupPayload($this->cat, ['teacher_rate' => 100000]), $this->admin);
        $a = $this->student($this->branch, ['name' => 'Ali']);
        $b = $this->student($this->branch, ['name' => 'Vali']);
        app(EnrollmentService::class)->enroll($group, $a, null, $this->admin);
        app(EnrollmentService::class)->enroll($group, $b, null, $this->admin);
        $pay = app(PaymentService::class);
        $pay->receive($a, ['cash' => 300000, 'card' => 200000], $group, null, $this->admin);
        $pay->receive($b, ['cash' => 100000], null, null, $this->admin);
        $pay->refund($a, PayMethod::Cash, 50000, 'Qaytarish', $this->admin);
        $cash = app(CashboxService::class);
        $cash->approve($cash->request(CashRequest::EXPENSE, PayMethod::Cash, 40000, "Qog'oz", $this->admin), $this->admin);
        $this->fund($this->branch, Wallet::TreasuryCash, 200000);
        app(FinanceService::class)->expense(PayMethod::Cash, 25000, 'Ijara', $this->admin);
        app(PayrollService::class)->payTeacher($this->cat['teacher'], null, PayMethod::Cash, 60000, 'Avans', $this->admin);
        Lead::create(['branch_id' => $this->branch->id, 'name' => 'L1', 'phone' => '+998 90 111 1111', 'status' => 'converted']);
        Lead::create(['branch_id' => $this->branch->id, 'name' => 'L2', 'phone' => '+998 90 222 2222', 'status' => 'new']);
        Lead::create(['branch_id' => $this->branch->id, 'name' => 'L3', 'phone' => '+998 90 333 3333', 'status' => 'in_progress']);
        Lead::create(['branch_id' => $this->branch->id, 'name' => 'L4', 'phone' => '+998 90 444 4444', 'status' => 'cancelled']);
    }

    public function test_trend_series_are_exact_and_grouped(): void
    {
        $this->scenario();
        $t = collect(app(StatisticsService::class)->trend(3))->keyBy('month');

        $sep = $t['2026-09'];
        $this->assertSame(400000, $sep['income_cash']);
        $this->assertSame(200000, $sep['income_card']);
        $this->assertSame(50000, $sep['refunds']);
        $this->assertSame(65000, $sep['expenses']);
        $this->assertSame(60000, $sep['salaries']);
        $this->assertSame(2, $sep['new_students']);
        $this->assertSame(4, $sep['new_leads']);
        $this->assertSame(1, $sep['converted_leads']);
        $this->assertSame(0, $t['2026-07']['income']);
    }

    public function test_income_series_daily_and_monthly(): void
    {
        $this->scenario();
        $svc = app(StatisticsService::class);

        $day = $svc->incomeSeries(CarbonImmutable::parse('2026-09-19'), CarbonImmutable::parse('2026-09-21'));
        $this->assertSame('day', $day['unit']);
        $this->assertCount(3, $day['rows']);
        $this->assertSame(['key' => '2026-09-21', 'cash' => 400000, 'card' => 200000], $day['rows'][2]);
        $this->assertSame(0, $day['rows'][0]['cash']);

        $month = $svc->incomeSeries(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-09-21'));
        $this->assertSame('month', $month['unit']);
        $this->assertSame(400000, collect($month['rows'])->firstWhere('key', '2026-09')['cash']);
    }

    public function test_top_groups_courses_debt_and_lead_statuses(): void
    {
        $this->scenario();
        $svc = app(StatisticsService::class);
        $range = [CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30')];

        $top = $svc->topGroups(...$range);
        $this->assertCount(1, $top);
        $this->assertSame(500000, $top[0]['net']);                        // guruh ko'rsatilgan to'lov (qaytarish guruhsiz)

        $this->assertSame(2, $svc->studentsByCourse()[0]['students']);

        $debt = $svc->debtBuckets();
        $this->assertSame(1, array_sum(array_column($debt, 'count')));
        $this->assertSame(400000, array_sum(array_column($debt, 'sum')));
        $this->assertSame(1, $debt[1]['count']);                          // 100–500 ming oralig'i

        $l = $svc->leadStatuses(...$range);
        $this->assertSame(['total' => 4, 'new' => 1, 'in_progress' => 1, 'converted' => 1, 'cancelled' => 1], $l);
    }

    public function test_statistics_page_renders_charts_with_table_twins(): void
    {
        $this->scenario();
        $html = $this->get('/statistics?from=2026-09-01&to=2026-09-30')->assertOk()
            ->assertSee('Tushum dinamikasi')->assertSee('Foyda dinamikasi')->assertSee('Varonka')->assertSee('Qarzdorlar')
            ->assertSee('data-spec=', false)->assertSee('Jadval')->getContent();
        $this->assertGreaterThanOrEqual(8, substr_count($html, '<canvas'));
        $this->assertStringContainsString('charts-', $html);          // Chart.js paketi sahifaga ulangan
    }

    public function test_statistics_hides_money_charts_without_permission(): void
    {
        $this->scenario();
        $viewer = $this->user(Role::Admin, $this->branch, ['statistics.view']);
        $this->actingAs($viewer)->get('/statistics')->assertOk()
            ->assertDontSee('Tushum dinamikasi')->assertDontSee('Foyda dinamikasi')->assertDontSee('Guruhlar bo&#039;yicha sof tushum', false)
            ->assertSee('O&#039;quvchi va murojaatlar', false);
    }

    public function test_sadmin_sees_branch_comparison_charts(): void
    {
        $this->scenario();
        $this->actingAs($this->user(Role::SAdmin))->get('/statistics')->assertOk()
            ->assertSee('Filiallar: sof tushum')->assertSee('Filiallar: o&#039;quvchilar', false);
    }

    public function test_viz_table_matches_spec(): void
    {
        $spec = Viz::column(['A', 'B'], [['label' => 'Naqt', 'data' => [1000, 2500]], ['label' => 'Plastik', 'data' => [500, 0]]], 'money', true);
        $t = Viz::table($spec);
        $this->assertSame(['Nomi', 'Naqt', 'Plastik'], $t['columns']);
        $this->assertSame(['A', "1 000 so'm", "500 so'm"], $t['rows'][0]);
        $this->assertSame(['Jami', "3 500 so'm", "500 so'm"], $t['foot']);
        $this->assertFalse(Viz::isEmpty($spec));
        $this->assertTrue(Viz::isEmpty(Viz::line(['x'], [['label' => 'q', 'data' => [0]]])));
        $this->assertSame('12,3 mln', Viz::short(12300000));
        $this->assertSame('Sen 26', Viz::label('2026-09'));
        $this->assertSame('05.09', Viz::label('2026-09-05'));
    }

    public function test_leads_page_shows_funnel_and_filter(): void
    {
        $this->scenario();
        $this->get('/leads')->assertOk()->assertSee('Murojaatlar voronkasi')->assertSee('<polygon', false)
            ->assertSee('Jami murojaat')->assertSee('Ko&#039;rib chiqilgan', false)->assertSee('Bekor qilingan');

        // Faqat oxirgi 30 kun ichidagi murojaatlar
        \App\Models\Lead::query()->update(['created_at' => now()->subDays(200)]);
        $this->get('/leads?days=30')->assertOk()->assertSee("Bu davrda murojaat yo'q", false);
        $this->get('/leads?days=365')->assertOk()->assertSee('<polygon', false);
    }

    public function test_attendance_stats_page_has_charts(): void
    {
        $this->scenario();
        $html = $this->get('/attendance/stats?month=2026-09')->assertOk()
            ->assertSee('Kelganlar va kelmaganlar')->assertSee('Guruhlar bo&#039;yicha davomad', false)->assertSee('Eng ko&#039;p qoldirayotgan', false)
            ->getContent();
        $this->assertStringNotContainsString('style="height: 3%', $html);   // eski ko'rinmas CSS ustunlar qolmagan
        $this->assertStringContainsString('charts-', $html);
    }
}
