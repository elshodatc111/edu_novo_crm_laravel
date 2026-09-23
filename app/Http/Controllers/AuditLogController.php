<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Support\BranchContext;
use App\Support\SafeInput;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Jurnal joriy filial doirasida ko'rinadi: admin faqat o'z filialini, sAdmin tanlangan filialni,
     * filial tanlanmagan bo'lsa (barcha filiallar rejimi) hammasini ko'radi va sahifada filial bo'yicha ham saralay oladi.
     */
    public function index(Request $request)
    {
        $this->authorize('audit.view');

        $allMode = BranchContext::id() === null;          // faqat sAdmin, filial tanlanmagan
        $branchFilter = $allMode ? SafeInput::string($request->input('branch'), '', 20) : '';

        $logs = AuditLog::visibleToContext()
            ->with(['user', 'branch'])
            ->when($allMode && $branchFilter === 'none', fn ($q) => $q->whereNull('audit_logs.branch_id'))
            ->when($allMode && ctype_digit($branchFilter) && $branchFilter !== '', fn ($q) => $q->where('audit_logs.branch_id', (int) $branchFilter))
            ->when(SafeInput::string($request->input('action')), fn ($q, $action) => $q->where('action', 'like', $action.'%'))
            ->when(SafeInput::string($request->input('user')), fn ($q, $name) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.$name.'%')))
            ->when(SafeInput::date($request->input('from')), fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when(SafeInput::date($request->input('to')), fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('staff.audit', [
            'logs' => $logs,
            'allMode' => $allMode,
            'branches' => $allMode ? Branch::orderBy('name')->get(['id', 'name']) : collect(),
            'scopeBranch' => BranchContext::id() ? Branch::find(BranchContext::id()) : null,
            'branchFilter' => $branchFilter,
        ]);
    }
}
