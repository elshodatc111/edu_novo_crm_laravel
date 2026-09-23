<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StatisticsController as WebStatistics;
use App\Services\StatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatisticsController extends Controller
{
    public function overview(Request $request, StatisticsService $stats): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('statistics.view'), 403);

        [$from, $to] = WebStatistics::period($request);
        $o = $stats->overview($from, $to);

        // Ruxsat berilmagan pul ko'rsatkichlari yashiriladi
        if (! $user->can('payments.view')) {
            $o = array_diff_key($o, array_flip(['income', 'income_cash', 'income_card', 'refunds', 'discounts', 'net_income', 'payments_count']));
        }
        if (! $user->can('finance.view')) {
            $o = array_diff_key($o, array_flip(['expenses', 'salaries', 'profit']));
        }

        return response()->json(['success' => true, 'data' => $o]);
    }
}
