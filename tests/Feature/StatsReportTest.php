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
use App\Services\ReportService;
use App\Services\StatisticsService;
use App\Support\Xlsx\XlsxReader;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StatsReportTest extends TestCase
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
        $this->admin = $this->user(Role::Admin, $this->branch, ['groups.create', 'groups.members', 'students.view', 'payments.view', 'payments.create', 'payments.refund', 'cashbox.view', 'cashbox.request', 'cashbox.approve', 'finance.view', 'finance.manage', 'teachers.view', 'teachers.pay', 'statistics.view', 'reports.view', 'reports.export', 'attendance.stats', 'leads.view']);
        $this->actingAs($this->admin);
        $this->cat = $this->catalog($this->branch);
    }

    /** Aniq ma'lum raqamli stsenariy. */
    private function scenario(): void
    {
        $group = app(GroupService::class)->create($this->groupPayload($this->cat, ['teacher_rate' => 100000]), $this->admin);
        $a = $this->student($this->branch, ['name' => 'Ali']);
        $b = $this->student($this->branch, ['name' => 'Vali']);
        app(EnrollmentService::class)->enroll($group, $a, null, $this->admin);   // -500 000
        app(EnrollmentService::class)->enroll($group, $b, null, $this->admin);   // -500 000

        $pay = app(PaymentService::class);
        $pay->receive($a, ['cash' => 300000, 'card' => 200000], $group, null, $this->admin);   // 500 000 -> chegirma 50 000
        $pay->receive($b, ['cash' => 100000], null, null, $this->admin);
        $pay->refund($a, PayMethod::Cash, 50000, 'Qaytarish', $this->admin);

        // Xarajat: kassadan 40 000 (tasdiqlangan) + moliyadan 25 000
        $cash = app(CashboxService::class);
        $req = $cash->request(CashRequest::EXPENSE, PayMethod::Cash, 40000, 'Qog\'oz', $this->admin);
        $cash->approve($req, $this->admin);
        $this->fund($this->branch, Wallet::TreasuryCash, 200000);
        app(FinanceService::class)->expense(PayMethod::Cash, 25000, 'Ijara', $this->admin);
        app(PayrollService::class)->payTeacher($this->cat['teacher'], null, PayMethod::Cash, 60000, 'Avans', $this->admin);

        Lead::create(['branch_id' => $this->branch->id, 'name' => 'L1', 'phone' => '+998 90 111 1111', 'status' => 'converted']);
        Lead::create(['branch_id' => $this->branch->id, 'name' => 'L2', 'phone' => '+998 90 222 2222', 'status' => 'new']);
    }

    public function test_overview_numbers_are_exact(): void
    {
        $this->scenario();
        $o = app(StatisticsService::class)->overview(CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30'));

        $this->assertSame(600000, $o['income']);
        $this->assertSame(400000, $o['income_cash']);
        $this->assertSame(200000, $o['income_card']);
        $this->assertSame(50000, $o['refunds']);
        $this->assertSame(550000, $o['net_income']);
        $this->assertSame(50000, $o['discounts']);
        $this->assertSame(65000, $o['expenses']);                        // 40 000 kassa + 25 000 moliya
        $this->assertSame(60000, $o['salaries']);
        $this->assertSame(550000 - 65000 - 60000, $o['profit']);
        $this->assertSame(3, $o['payments_count']);
        $this->assertSame(2, $o['new_students']);
        $this->assertSame(2, $o['active_students']);
        $this->assertSame(2, $o['new_leads']);
        $this->assertSame(1, $o['converted_leads']);
        $this->assertEquals(50.0, $o['lead_conversion']);

        // A: -500 000 + 500 000 + 50 000 chegirma - 50 000 qaytarish = 0 ; B: -400 000
        $this->assertSame(1, $o['debtors']);
        $this->assertSame(400000, $o['debt_total']);

        // Boshqa davr — pul harakati yo'q, lekin qarz hozirgi holat
        $empty = app(StatisticsService::class)->overview(CarbonImmutable::parse('2026-08-01'), CarbonImmutable::parse('2026-08-31'));
        $this->assertSame(0, $empty['income']);
        $this->assertSame(0, $empty['new_students']);
        $this->assertSame(400000, $empty['debt_total']);
        $this->assertNull($empty['lead_conversion']);
    }

    public function test_overview_is_scoped_to_branch_and_sadmin_sees_all(): void
    {
        $this->scenario();
        $other = $this->branch('Boshqa');
        $adminB = $this->user(Role::Admin, $other, ['payments.create']);
        $this->actingAs($adminB);
        app(PaymentService::class)->receive($this->student($other), ['cash' => 999000], null, null, $adminB);

        $range = [CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30')];

        $this->actingAs($this->admin);
        $this->assertSame(600000, app(StatisticsService::class)->overview(...$range)['income']);

        $this->actingAs($this->user(Role::SAdmin));
        $this->assertSame(1599000, app(StatisticsService::class)->overview(...$range)['income']);

        $cmp = collect(app(StatisticsService::class)->branchComparison(...$range));
        $this->assertSame(550000, $cmp->firstWhere('branch', 'Toshkent')['net_income']);
        $this->assertSame(999000, $cmp->firstWhere('branch', 'Boshqa')['net_income']);
        $this->assertSame(400000, $cmp->firstWhere('branch', 'Toshkent')['debt']);
    }

    public function test_trend_has_requested_months(): void
    {
        $this->scenario();
        $trend = app(StatisticsService::class)->trend(6);

        $this->assertCount(6, $trend);
        $this->assertSame('2026-04', $trend[0]['month']);
        $this->assertSame('2026-09', $trend[5]['month']);
        $this->assertSame(550000, $trend[5]['net_income']);
        $this->assertSame(0, $trend[0]['income']);
    }

    public function test_reports_content_matches_statistics(): void
    {
        $this->scenario();
        $svc = app(ReportService::class);
        $from = CarbonImmutable::parse('2026-09-01');
        $to = CarbonImmutable::parse('2026-09-30');

        $p = $svc->build('payments', $from, $to);
        $this->assertSame(550000, $p['summary']['Sof tushum']);
        $this->assertSame(400000, $p['summary']['Naqt tushum']);
        $this->assertSame(50000, $p['summary']['Chegirma va bonus']);
        $this->assertCount(5, $p['rows']);   // A: naqt + plastik, chegirma; B: naqt; A: qaytarish

        $d = $svc->build('debtors', $from, $to);
        $this->assertSame(1, $d['summary']['Qarzdorlar soni']);
        $this->assertSame(400000, $d['summary']['Umumiy qarz']);
        $this->assertSame('VALI', mb_strtoupper($d['rows'][0][0]));

        $l = $svc->build('leads', $from, $to);
        $this->assertSame('50%', str_replace('.0', '', $l['summary']['Qabul foizi']));

        $c = $svc->build('cashflow', $from, $to);
        $this->assertGreaterThan(0, $c['summary']['Chiqim']);

        $pr = $svc->build('payroll', $from, $to);
        $this->assertSame(60000, $pr['summary']["Davrda to'langan"]);

        foreach (array_keys(ReportService::REPORTS) as $key) {
            $this->assertIsArray($svc->build($key, $from, $to)['rows']);
        }
    }

    public function test_pages_export_and_permissions(): void
    {
        $this->scenario();

        $this->get('/statistics?from=2026-09-01&to=2026-09-30')->assertOk()->assertSee('Sof tushum')->assertSee('550 000');
        $this->get('/reports')->assertOk()->assertSee("To'lovlar");
        $this->get('/reports/payments?from=2026-09-01&to=2026-09-30')->assertOk()->assertSee('Ali');

        $response = $this->get('/reports/debtors/export');
        $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $tmp = tempnam(sys_get_temp_dir(), 'r').'.xlsx';
        file_put_contents($tmp, $response->baseResponse->getFile()->getContent());
        $rows = XlsxReader::read($tmp, 'xlsx');
        unlink($tmp);
        $this->assertSame('Qarzdorlar hisoboti', $rows[0][0]);
        $this->assertContains('Qarz (so\'m)', $rows[1]);
        $this->assertSame('400000', $rows[2][4]);
        $this->assertSame('Umumiy qarz', collect($rows)->firstWhere(0, 'Umumiy qarz')[0]);

        // Ruxsatlar
        $noExport = $this->user(Role::Manager, $this->branch, ['reports.view', 'statistics.view']);
        $this->actingAs($noExport)->get('/reports/debtors')->assertOk();
        $this->actingAs($noExport)->get('/reports/debtors/export')->assertForbidden();
        $this->actingAs($noExport)->get('/reports/cashflow')->assertForbidden();
        $this->actingAs($noExport)->get('/reports/yoq')->assertNotFound();

        $none = $this->user(Role::Manager, $this->branch, ['students.view']);
        $this->actingAs($none)->get('/statistics')->assertForbidden();
        $this->actingAs($none)->get('/reports')->assertForbidden();
    }

    public function test_statistics_hide_money_without_permission(): void
    {
        $this->scenario();
        $viewer = $this->user(Role::Manager, $this->branch, ['statistics.view']);

        $this->actingAs($viewer)->get('/statistics')->assertOk()->assertDontSee('Sof tushum')->assertDontSee('Foyda (taxminiy)')->assertSee('Qarzdorlar');
    }

    /**
     * v8: storno qilingan to'lov va rad etilgan qaytarish HECH QAYSI hisobot/statistika/panelda
     * hisoblanmasligi kerak — go'yo bo'lmagandek. Faqat to'g'ri qolgan yozuv (200 000 naqt) ko'rinishi kerak.
     */
    public function test_reversed_and_rejected_amounts_are_excluded_everywhere(): void
    {
        $group = app(GroupService::class)->create($this->groupPayload($this->cat), $this->admin);   // narx 500 000, guruhga qo'shilmagan (balans musbat qoladi)
        $a = $this->student($this->branch, ['name' => 'Ali']);

        $pay = app(PaymentService::class);
        $pay->receive($a, ['cash' => 200000], $group, null, $this->admin);                          // to'g'ri qoladi (naqt)
        [$badPayment] = $pay->receive($a->fresh(), ['card' => 150000], null, null, $this->admin);    // xato, storno qilinadi (plastik)
        $refund = $pay->refund($a->fresh(), PayMethod::Cash, 30000, "Qaytarish", $this->admin);      // keyin rad etiladi

        $pay->reverse($badPayment, "Xato kiritilgan", $this->admin);
        $pay->rejectRefund($refund, "Talaba fikridan qaytdi", $this->admin);

        $from = CarbonImmutable::parse('2026-09-01');
        $to = CarbonImmutable::parse('2026-09-30');

        $o = app(StatisticsService::class)->overview($from, $to);
        $this->assertSame(200000, $o['income']);
        $this->assertSame(200000, $o['income_cash']);
        $this->assertSame(0, $o['income_card']);
        $this->assertSame(0, $o['refunds']);
        $this->assertSame(200000, $o['net_income']);
        $this->assertSame(1, $o['payments_count']);

        $trend = app(StatisticsService::class)->trend(1);
        $this->assertSame(200000, $trend[0]['income']);
        $this->assertSame(0, $trend[0]['refunds']);

        $series = app(StatisticsService::class)->incomeSeries($from, $to);
        $this->assertSame(200000, collect($series['rows'])->sum('cash'));
        $this->assertSame(0, collect($series['rows'])->sum('card'));

        $top = collect(app(StatisticsService::class)->topGroups($from, $to));
        $this->assertSame(200000, $top->firstWhere('group_id', $group->id)['net']);

        $cmp = collect(app(StatisticsService::class)->branchComparison($from, $to));
        $this->assertSame(200000, $cmp->firstWhere('branch', $this->branch->name)['net_income']);

        $r = app(ReportService::class)->build('payments', $from, $to);
        $this->assertSame(200000, $r['summary']['Naqt tushum']);
        $this->assertSame(0, $r['summary']['Plastik tushum']);
        $this->assertSame(0, $r['summary']['Qaytarilgan']);
        $this->assertSame(200000, $r['summary']['Sof tushum']);

        $this->get('/payments')->assertOk()->assertViewHas('totals', fn ($t) => (int) $t->cash === 200000 && (int) $t->card === 0 && (int) $t->refunds === 0);
        $this->get('/cashbox')->assertOk()->assertViewHas('today', fn ($t) => (int) $t->cash === 200000 && (int) $t->card === 0);
        $this->get('/')->assertOk()->assertViewHas('money', fn ($m) => $m['income'] === 200000 && $m['refunds'] === 0);
    }
}
