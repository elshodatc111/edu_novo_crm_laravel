<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\User;
use App\Services\CalendarService;
use App\Services\DashboardService;
use App\Support\BranchContext;
use App\Support\SafeInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, CalendarService $calendar, DashboardService $dashboard)
    {
        $user = auth()->user();

        $staffCounts = User::visibleToContext()
            ->whereIn('role', [Role::Admin, Role::Manager, Role::Teacher, Role::Operator])
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $branches = $user->isSuperAdmin()
            ? Branch::withCount([
                'users as staff_count' => fn ($q) => $q->whereIn('role', [Role::Admin, Role::Manager, Role::Teacher, Role::Operator]),
                'users as students_count' => fn ($q) => $q->where('role', Role::Student),
            ])->orderBy('name')->get()
            : collect();

        $money = null;
        if ($user->can('payments.view')) {
            $sums = Payment::whereDate('created_at', today())->selectRaw("
                coalesce(sum(case when type = 'payment' and reversed_at is null then amount end), 0) as income,
                coalesce(sum(case when type = 'refund' and refund_rejected_at is null then amount end), 0) as refunds
            ")->first();
            $money = [
                'income' => (int) $sums->income,
                'refunds' => (int) $sums->refunds,
                'debtors' => User::visibleToContext()->where('role', Role::Student)->whereNull('archived_at')->where('balance', '<', 0)->count(),
                'debt' => -1 * (int) User::visibleToContext()->where('role', Role::Student)->whereNull('archived_at')->where('balance', '<', 0)->sum('balance'),
            ];
        }

        // Kalendar: hamma hodim ko'radi, lekin filial tanlangan bo'lishi kerak (sAdmin filial tanlamagan bo'lsa ko'rsatilmaydi)
        $cal = null;
        if (BranchContext::id()) {
            $m = SafeInput::month($request->input('cal'), today()->format('Y-m'));
            $cal = $calendar->month($m, $user);
        }

        return view('dashboard.index', [
            'calendar' => $cal,
            'money' => $money,
            'staffCounts' => $staffCounts,
            'branches' => $branches,
            'currentBranch' => $user->isSuperAdmin()
                ? (BranchContext::id() ? Branch::find(BranchContext::id()) : null)
                : $user->branch,
            'todo' => $dashboard->todo($user),
            'weeklyActivity' => ($user->isSuperAdmin() || $user->role === Role::Admin) ? $dashboard->weeklyActivity() : null,
            'groupStats' => $user->can('groups.view') ? $dashboard->groupStats() : null,
        ]);
    }

    /** Kunlik vazifalar panelida "Bajardim" — faqat bugunga yashiradi. */
    public function dismissTask(Request $request, DashboardService $dashboard): RedirectResponse
    {
        $data = $request->validate(['task_key' => ['required', 'string', 'max:120']]);

        $dashboard->dismiss($request->user(), $data['task_key']);

        return back()->with('success', 'Vazifa bugunga yashirildi.');
    }
}
