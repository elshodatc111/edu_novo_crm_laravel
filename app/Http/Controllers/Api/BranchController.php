<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * v11: sAdmin/operator uchun filiallar ro'yxati (mobilda "filial almashtirish"). Mobil API
 * stateless bo'lgani uchun (sessiya emas), tanlangan filial keyingi so'rovlarda
 * `X-Branch-Id` sarlavhasi orqali yuboriladi - bu yerda faqat ro'yxat va joriy holat qaytadi.
 */
class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->role === Role::SAdmin || $user->can('staff.view_all_branches'), 403);

        return response()->json(['success' => true, 'data' => Branch::orderBy('name')->get(['id', 'name', 'status'])]);
    }
}
