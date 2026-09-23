<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Requests\StaffRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\PermissionAssignmentService;
use App\Support\SafeInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Admin, menejer va o'qituvchilarni boshqarish. */
class StaffController extends Controller
{
    private const ROLES = [Role::Admin, Role::Manager, Role::Teacher, Role::Operator];

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $users = User::visibleToContext()
            ->with('branch')
            ->whereIn('role', UserPolicy::viewableRoles($request->user()))
            ->when(SafeInput::string($request->input('role')), fn ($q, $role) => $q->where('role', $role))
            ->when(SafeInput::string($request->input('status')), fn ($q, $status) => $q->where('status', $status))
            ->search(SafeInput::string($request->input('q')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('staff.index', [
            'users' => $users,
            'roles' => UserPolicy::viewableRoles($request->user()),
            'canCreate' => $this->creatableRoles() !== [],
        ]);
    }

    public function create()
    {
        abort_if($this->creatableRoles() === [], 403);

        return view('staff.form', [
            'staff' => new User(['status' => UserStatus::Active]),
            'roles' => $this->creatableRoles(),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function store(StaffRequest $request, PermissionAssignmentService $permissions): RedirectResponse
    {
        $data = $request->validated();
        $role = Role::from($data['role']);

        $this->authorize('create', [User::class, $role]);

        $data['branch_id'] = $request->user()->isSuperAdmin() ? $data['branch_id'] : $request->user()->branch_id;

        $staff = User::create($data);
        $permissions->giveDefaults($request->user(), $staff);

        AuditLog::record('staff.created', $staff, "Yangi hodim qo'shildi: {$staff->name} ({$role->label()})");

        return redirect()->route('staff.index')->with('success', "{$staff->name} qo'shildi.");
    }

    public function edit(User $user)
    {
        $this->authorize('manage', $user);

        return view('staff.form', [
            'staff' => $user,
            'roles' => [],
            'branches' => [],
        ]);
    }

    public function update(StaffRequest $request, User $user): RedirectResponse
    {
        $this->authorize('manage', $user);

        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        // Bloklangan foydalanuvchining barcha mobil tokenlari bekor qilinadi
        if (! $user->isActive()) {
            $user->tokens()->delete();
        }

        AuditLog::record('staff.updated', $user, "Hodim ma'lumotlari yangilandi: {$user->name}");

        return redirect()->route('staff.index')->with('success', "{$user->name} ma'lumotlari saqlandi.");
    }

    /** @return array<int, Role> */
    private function creatableRoles(): array
    {
        return array_values(array_filter(
            UserPolicy::manageableRoles(auth()->user()),
            fn (Role $r) => in_array($r, self::ROLES, true)
        ));
    }

    private function branchOptions()
    {
        return auth()->user()->isSuperAdmin() ? Branch::active()->orderBy('name')->get() : collect();
    }
}
