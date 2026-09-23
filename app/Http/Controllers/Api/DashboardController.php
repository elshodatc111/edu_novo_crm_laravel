<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** v11: mobil ilovada bosh sahifa vazifalari (qarzdorlar, javobsiz lidlar, guruh statistikasi). */
class DashboardController extends Controller
{
    public function todo(Request $request, DashboardService $dashboard): JsonResponse
    {
        $user = $request->user();

        return response()->json(['success' => true, 'data' => [
            'todo' => $dashboard->todo($user),
            'group_stats' => $user->can('groups.view') ? $dashboard->groupStats() : null,
        ]]);
    }
}
