<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Concerns\BranchScope;
use App\Models\Group;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** v11: mobil ilovada v9/v10'dagi "boshqa filiallar" (faqat ko'rish) imkoniyati. */
class StaffDirectoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('staff.view_all_branches');

        $staff = User::whereIn('role', [Role::SAdmin, Role::Admin, Role::Manager, Role::Operator, Role::Teacher])
            ->where('status', 'active')->with('branch:id,name')
            ->orderBy('branch_id')->orderBy('name')
            ->get(['id', 'name', 'role', 'phone', 'branch_id'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->role->value, 'role_label' => $u->role->label(), 'phone' => $u->phone, 'branch' => $u->branch?->name, 'branch_id' => $u->branch_id]);

        return response()->json(['success' => true, 'data' => $staff]);
    }

    /** Boshqa filialning o'quvchi/guruh/lidlarini FAQAT KO'RISH - yozish yo'q. */
    public function branch(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('staff.view_all_branches');

        $students = User::where('branch_id', $branch->id)->where('role', Role::Student)->whereNull('archived_at')
            ->orderBy('name')->paginate(15, ['id', 'name', 'phone', 'balance'], 'students_page')->withQueryString();

        $groups = Group::withoutGlobalScope(BranchScope::class)->where('branch_id', $branch->id)
            ->with(['course' => fn ($q) => $q->withoutGlobalScope(BranchScope::class)->select('id', 'name')])
            ->orderByDesc('starts_on')->paginate(15, ['*'], 'groups_page')->withQueryString();

        $leads = Lead::withoutGlobalScope(BranchScope::class)->where('branch_id', $branch->id)
            ->with(['source' => fn ($q) => $q->withoutGlobalScope(BranchScope::class)->select('id', 'name')])
            ->latest('created_at')->paginate(15, ['*'], 'leads_page')->withQueryString();

        return response()->json(['success' => true, 'data' => [
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'students' => $students->getCollection()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'phone' => $s->phone, 'balance' => $s->balance]),
            'groups' => $groups->getCollection()->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'course' => $g->course?->name, 'status' => $g->status]),
            'leads' => $leads->getCollection()->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'phone' => $l->phone, 'status' => $l->status, 'source' => $l->source?->name]),
        ], 'meta' => [
            'students_last_page' => $students->lastPage(), 'groups_last_page' => $groups->lastPage(), 'leads_last_page' => $leads->lastPage(),
        ]]);
    }
}
