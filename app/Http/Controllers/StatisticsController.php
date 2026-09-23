<?php

namespace App\Http\Controllers;

use App\Services\StatisticsService;
use App\Support\Viz;
use App\Support\BranchContext;
use App\Support\SafeInput;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class StatisticsController extends Controller
{
    public function index(Request $request, StatisticsService $stats)
    {
        $this->authorize('statistics.view');

        [$from, $to] = self::period($request);
        $user = $request->user();

        $showMoney = $user->can('payments.view');
        $showFinance = $user->can('finance.view');
        $o = $stats->overview($from, $to);
        $trend = $stats->trend(12);
        $charts = $this->charts($stats, $from, $to, $o, $trend, $showMoney, $showFinance);

        $comparison = $user->isSuperAdmin() && BranchContext::id() === null ? $stats->branchComparison($from, $to) : [];
        if ($comparison && $showMoney) {
            $charts['branches'] = Viz::column(array_column($comparison, 'branch'), [
                ['label' => 'Sof tushum', 'data' => array_column($comparison, 'net_income'), 'slot' => 1],
            ], 'money', nameColumn: 'Filial');
            $charts['branch_students'] = Viz::column(array_column($comparison, 'branch'), [
                ['label' => "Faol o'quvchi", 'data' => array_column($comparison, 'students'), 'slot' => 3],
                ['label' => "Yangi o'quvchi", 'data' => array_column($comparison, 'new_students'), 'slot' => 2],
            ], nameColumn: 'Filial');
        }

        return view('statistics.index', [
            'from' => $from, 'to' => $to, 'o' => $o, 'trend' => $trend, 'charts' => $charts,
            'comparison' => $comparison, 'showMoney' => $showMoney, 'showFinance' => $showFinance,
        ]);
    }

    /**
     * Har bir dashboard uchun grafik spec'i (jadval ko'rinishi shu spec'dan quriladi).
     *
     * @return array<string, mixed>
     */
    private function charts(StatisticsService $stats, CarbonImmutable $from, CarbonImmutable $to, array $o, array $trend, bool $money, bool $finance): array
    {
        $c = [];
        $months = array_map(fn ($t) => Viz::label($t['month']), $trend);

        if ($money) {
            $inc = $stats->incomeSeries($from, $to);
            $c['income'] = Viz::column(
                array_map(fn ($r) => Viz::label($r['key']), $inc['rows']),
                [['label' => 'Naqt', 'data' => array_column($inc['rows'], 'cash'), 'slot' => 1], ['label' => 'Plastik', 'data' => array_column($inc['rows'], 'card'), 'slot' => 2]],
                'money', stacked: true, nameColumn: $inc['unit'] === 'day' ? 'Kun' : 'Oy',
            );
            $c['income_unit'] = $inc['unit'];
            $c['method'] = Viz::donut(['Naqt', 'Plastik'], [$o['income_cash'], $o['income_card']], 'money', ['value' => Viz::short($o['income']), 'label' => 'tushum'], [null, null], 'Tushum', "To'lov usuli");
            $top = $stats->topGroups($from, $to);
            $c['top_groups'] = Viz::bar(array_column($top, 'name'), [['label' => 'Sof tushum', 'data' => array_column($top, 'net'), 'slot' => 1]], 'money', nameColumn: 'Guruh');
            $c['top_groups_ids'] = array_column($top, 'group_id');
        }

        if ($finance) {
            $c['flow'] = Viz::column($months, [
                ['label' => 'Sof tushum', 'data' => array_column($trend, 'net_income'), 'slot' => 1],
                ['label' => 'Xarajat', 'data' => array_column($trend, 'expenses'), 'slot' => 2],
                ['label' => 'Ish haqi', 'data' => array_column($trend, 'salaries'), 'slot' => 4],
            ], 'money', nameColumn: 'Oy');
            $c['profit'] = Viz::line($months, [[
                'label' => 'Foyda', 'slot' => 3,
                'data' => array_map(fn ($t) => $t['net_income'] - $t['expenses'] - $t['salaries'], $trend),
            ]], 'money', true, 'Oy');
        } elseif ($money) {
            $c['flow'] = Viz::column($months, [['label' => 'Sof tushum', 'data' => array_column($trend, 'net_income'), 'slot' => 1]], 'money', nameColumn: 'Oy');
        }

        $c['people'] = Viz::line($months, [
            ['label' => "Yangi o'quvchi", 'data' => array_column($trend, 'new_students'), 'slot' => 3],
            ['label' => 'Murojaat', 'data' => array_column($trend, 'new_leads'), 'slot' => 1],
            ['label' => 'Qabul qilingan', 'data' => array_column($trend, 'converted_leads'), 'slot' => 2],
        ], 'int', false, 'Oy');

        $courses = $stats->studentsByCourse();
        $c['courses'] = Viz::bar(array_column($courses, 'name'), [['label' => "Faol o'quvchi", 'data' => array_column($courses, 'students'), 'slot' => 3]], 'int', nameColumn: 'Kurs');

        $debt = $stats->debtBuckets();
        $c['debt'] = Viz::column(array_column($debt, 'label'), [['label' => 'Qarzdor soni', 'data' => array_column($debt, 'count'), 'slot' => 8]], 'int', nameColumn: 'Qarz miqdori');
        $c['debt_table'] = [
            'columns' => ['Qarz miqdori', 'Qarzdorlar', 'Qarz summasi'],
            'rows' => array_map(fn ($d) => [$d['label'], Viz::format('int', $d['count']), Viz::format('money', $d['sum'])], $debt),
            'foot' => ['Jami', Viz::format('int', array_sum(array_column($debt, 'count'))), Viz::format('money', array_sum(array_column($debt, 'sum')))],
        ];

        $att = $stats->attendanceByMonth(6);
        $c['attendance'] = Viz::line(array_map(fn ($k) => Viz::label($k), array_keys($att)), [['label' => 'Davomad', 'data' => array_values($att), 'slot' => 6]], 'percent', true, 'Oy');

        $c['funnel'] = $stats->leadStatuses($from, $to);

        return $c;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function period(Request $request): array
    {
        $from = self::date(SafeInput::string($request->input('from')), CarbonImmutable::today()->startOfMonth());
        $to = self::date(SafeInput::string($request->input('to')), CarbonImmutable::today());

        return $from->gt($to) ? [$to, $from] : [$from, $to];
    }

    private static function date(?string $value, CarbonImmutable $default): CarbonImmutable
    {
        try {
            return $value ? CarbonImmutable::parse($value)->startOfDay() : $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}
