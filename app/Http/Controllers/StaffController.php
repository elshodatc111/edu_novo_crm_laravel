<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Requests\StaffRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Group;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\PermissionAssignmentService;
use App\Support\SafeInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            'extraBranchOptions' => auth()->user()->isSuperAdmin() ? Branch::active()->orderBy('name')->get() : collect(),
            'extraBranchIds' => [],
        ]);
    }

    public function store(StaffRequest $request, PermissionAssignmentService $permissions): RedirectResponse
    {
        $data = $request->validated();
        $extra = array_map('intval', (array) ($data['extra_branches'] ?? []));
        unset($data['extra_branches'], $data['extra_branches_form']);
        $role = Role::from($data['role']);

        $this->authorize('create', [User::class, $role]);

        $data['branch_id'] = $request->user()->isSuperAdmin() ? $data['branch_id'] : $request->user()->branch_id;

        $staff = User::create($data);
        $permissions->giveDefaults($request->user(), $staff);

        if ($role === Role::Operator && $request->user()->isSuperAdmin()) {
            $this->syncExtraBranches($staff, $request->user(), $extra);
        }

        AuditLog::record('staff.created', $staff, "Yangi hodim qo'shildi: {$staff->name} ({$role->label()})");

        return redirect()->route('staff.index')->with('success', "{$staff->name} qo'shildi.");
    }

    public function edit(User $user)
    {
        $this->authorize('manage', $user);

        return view('staff.form', [
            'staff' => $user,
            'roles' => [],
            // v13: lavozimni o'zgartirish ro'yxati (hozirgisi bilan birga); bo'sh bo'lsa tanlagich chiqmaydi
            'extraBranchOptions' => auth()->user()->isSuperAdmin() && $user->role === Role::Operator
                ? Branch::active()->where('id', '!=', $user->branch_id)->orderBy('name')->get() : collect(),
            'extraBranchIds' => DB::table('user_branches')->where('user_id', $user->id)->pluck('branch_id')->map(fn ($v) => (int) $v)->all(),
            'changeRoles' => ($options = UserPolicy::assignableRoles(auth()->user(), $user)) === [] ? [] : [$user->role, ...$options],
            'branches' => [],
        ]);
    }

    public function update(StaffRequest $request, User $user, PermissionAssignmentService $permissions): RedirectResponse
    {
        $this->authorize('manage', $user);

        $data = $request->validated();
        $extra = array_map('intval', (array) ($data['extra_branches'] ?? []));
        $extraSubmitted = array_key_exists('extra_branches_form', $data);
        unset($data['extra_branches'], $data['extra_branches_form']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $newRole = ! empty($data['role']) ? Role::from($data['role']) : null;
        unset($data['role']);
        $roleChanged = $newRole !== null && $newRole !== $user->role;

        if ($roleChanged) {
            $this->authorize('changeRole', [$user, $newRole]);

            // O'qituvchi tugamagan guruhga biriktirilgan bo'lsa, lavozimini o'zgartirib bo'lmaydi
            if ($user->role === Role::Teacher && Group::where('teacher_id', $user->id)->whereDate('ends_on', '>=', today())->exists()) {
                throw ValidationException::withMessages(['role' => "Bu o'qituvchining tugamagan guruhlari bor. Avval guruhlarini boshqa o'qituvchiga o'tkazing."]);
            }
        }

        $oldRole = $user->role;

        DB::transaction(function () use ($user, $data, $roleChanged, $newRole, $permissions, $request) {
            $user->update($data);

            if ($roleChanged) {
                $user->role = $newRole;
                $user->save();
                // Ruxsatlar yangi lavozim shabloniga qaytariladi (ijrochi vakolati doirasida)
                $permissions->giveDefaults($request->user(), $user);
            }
        });

        // v13: operatorga qo'shimcha filiallar (faqat sAdmin). Lavozim operatordan boshqasiga o'tsa - hammasi olib tashlanadi.
        if ($user->role !== Role::Operator) {
            $this->syncExtraBranches($user, $request->user(), [], force: true);
        } elseif ($request->user()->isSuperAdmin() && $extraSubmitted) {
            $this->syncExtraBranches($user, $request->user(), $extra);
        }

        if ($roleChanged) {
            // Eski lavozim huquqlari bilan berilgan mobil seanslar tugatiladi
            $user->tokens()->delete();

            AuditLog::record(
                'staff.role_changed',
                $user,
                "Lavozim o'zgartirildi: {$user->name} ({$oldRole->label()} → {$newRole->label()})",
                ['role' => $oldRole->value],
                ['role' => $newRole->value],
            );
        }

        // Bloklangan foydalanuvchining barcha mobil tokenlari bekor qilinadi
        if (! $user->isActive()) {
            $user->tokens()->delete();
        }

        AuditLog::record('staff.updated', $user, "Hodim ma'lumotlari yangilandi: {$user->name}");

        return redirect()->route('staff.index')->with('success', $roleChanged
            ? "{$user->name} endi {$newRole->label()}. Ruxsatlari yangi lavozim shabloniga qaytarildi, ularni «Ruxsatlar» bo'limida tekshiring."
            : "{$user->name} ma'lumotlari saqlandi.");
    }

    /**
     * v13: operatorning qo'shimcha filiallarini to'liq almashtiradi (o'z filiali qo'shilmaydi). Faqat sAdmin.
     * Olib tashlangan filialdagi ochiq seanslar keyingi so'rovdayoq o'z filialiga qaytadi (BranchContext tekshiradi).
     *
     * @param  array<int, int>  $ids
     */
    private function syncExtraBranches(User $operator, User $actor, array $ids, bool $force = false): void
    {
        if (! $actor->isSuperAdmin() && ! $force) {
            return;
        }

        $ids = array_values(array_unique(array_filter($ids, fn ($id) => $id > 0 && $id !== (int) $operator->branch_id)));
        $current = DB::table('user_branches')->where('user_id', $operator->id)->pluck('branch_id')->map(fn ($v) => (int) $v)->all();

        if (sort($ids) && sort($current) && $ids === $current) {
            return;
        }

        DB::transaction(function () use ($operator, $actor, $ids) {
            DB::table('user_branches')->where('user_id', $operator->id)->delete();
            foreach ($ids as $id) {
                DB::table('user_branches')->insert(['user_id' => $operator->id, 'branch_id' => $id, 'granted_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        \App\Support\BranchContext::forgetExtra($operator);

        $names = Branch::whereIn('id', $ids)->orderBy('name')->pluck('name')->implode(', ');
        AuditLog::record('staff.branches_changed', $operator, "Operatorning qo'shimcha filiallari o'zgartirildi: {$operator->name} → ".($names ?: 'yo\'q'));
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
