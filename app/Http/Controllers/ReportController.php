<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Support\Xlsx\XlsxWriter;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private const DISPLAY_LIMIT = 500;

    public function index(Request $request)
    {
        abort_unless($request->user()->can('reports.view'), 403);

        $available = collect(ReportService::REPORTS)->filter(fn ($def) => $request->user()->can($def[1]));

        return view('reports.index', ['reports' => $available]);
    }

    public function show(Request $request, string $report, ReportService $service)
    {
        $def = $this->definition($request, $report);
        [$from, $to] = StatisticsController::period($request);

        $data = $service->build($report, $from, $to);

        return view('reports.show', [
            'key' => $report, 'name' => $def[0], 'needsPeriod' => $def[2], 'from' => $from, 'to' => $to,
            'data' => $data, 'shown' => array_slice($data['rows'], 0, self::DISPLAY_LIMIT),
            'canExport' => $request->user()->can('reports.export'),
        ]);
    }

    public function export(Request $request, string $report, ReportService $service)
    {
        $this->definition($request, $report);
        abort_unless($request->user()->can('reports.export'), 403);

        [$from, $to] = StatisticsController::period($request);
        $data = $service->build($report, $from, $to);

        $rows = $data['rows'];
        if ($data['summary']) {
            $rows[] = [];
            foreach ($data['summary'] as $label => $value) {
                $rows[] = [$label, is_numeric($value) ? $value + 0 : $value];
            }
        }

        $title = $data['title'].(ReportService::REPORTS[$report][2] ? " ({$from->format('d.m.Y')} — {$to->format('d.m.Y')})" : '');
        $path = XlsxWriter::write($data['title'], $data['columns'], $rows, $title);

        \App\Models\AuditLog::record('report.exported', null, "Hisobot yuklab olindi: {$data['title']}");

        return response()->download($path, "hisobot-{$report}-{$from->format('Ymd')}-{$to->format('Ymd')}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function definition(Request $request, string $report): array
    {
        abort_unless(isset(ReportService::REPORTS[$report]), 404);
        $def = ReportService::REPORTS[$report];
        abort_unless($request->user()->can('reports.view') && $request->user()->can($def[1]), 403);

        return $def;
    }
}
