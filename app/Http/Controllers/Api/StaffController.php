<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\StaffRequest;
use App\Models\AuditLog;
use App\Models\Payout;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\PayrollService;
use App\Services\PermissionAssignmentService;
use App\Support\BranchContext;
use App\Support\SafeInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * v11: mobil ilovada Hodimlar. Ish haqi TO'LASH (pul chiqishi) ataylab mobilga chiqarilmagan -
 * veb'da ikki bosqichli tasdiqlash bilan himoyalangan (PayrollController::pay); mobilda faqat
 * ro'yxat, ko'rish va hisoblangan ish haqini KO'RISH bor.
 */
class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::visibleToContext()->with('branch:id,name')
            ->whereIn('role', UserPolicy::viewableRoles($request->user()))
            ->when(SafeInput::string($request->input('role')), fn ($q, $role) => $q->where('role', $role))
            ->when(SafeInput::string($request->input('q')), fn ($q, $term) => $q->search($term))
            ->orderBy('name')->paginate(min(50, max(5, $request->integer('per_page', 20))));

        return response()->json(['success' => true, 'data' => $users->getCollection()->map(fn ($u) => $this->row($u)), 'meta' => [
            'page' => $users->currentPage(), 'last_page' => $users->lastPage(), 'total' => $users->total(),
        ]]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('manage', $user);

        return response()->json(['success' => true, 'data' => $this->row($user)]);
    }

    public function store(StaffRequest $request, PermissionAssignmentService $permissions): JsonResponse
    {
        $data = $request->validated();
        $role = Role::from($data['role']);

        $this->authorize('create', [User::class, $role]);

        // v12 B: API'da branch_id endi so'rov tanasida kelmaydi (StaffRequest'da 'prohibited') -
        // sAdmin uchun ham BranchContext'dan (X-Branch-Id) olinadi, 'branch' middleware buni
        // routes/api.php'da allaqachon tekshirgan (null bo'lishi mumkin emas).
        $data['branch_id'] = $request->user()->isSuperAdmin() ? BranchContext::id() : $request->user()->branch_id;

        $staff = User::create($data);
        $permissions->giveDefaults($request->user(), $staff);

        AuditLog::record('staff.created', $staff, "Yangi hodim qo'shildi: {$staff->name} ({$role->label()})");

        return response()->json(['success' => true, 'message' => "{$staff->name} qo'shildi.", 'data' => $this->row($staff)], 201);
    }

    public function update(StaffRequest $request, User $user): JsonResponse
    {
        $this->authorize('manage', $user);

        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        if (! $user->isActive()) {
            $user->tokens()->delete();
        }

        AuditLog::record('staff.updated', $user, "Hodim ma'lumotlari yangilandi: {$user->name}");

        return response()->json(['success' => true, 'message' => "{$user->name} ma'lumotlari saqlandi.", 'data' => $this->row($user)]);
    }

    /** Hisoblangan ish haqi va to'lovlar tarixi (faqat ko'rish - to'lash veb orqali). */
    public function payroll(Request $request, User $user, PayrollService $payroll): JsonResponse
    {
        $isTeacher = $user->role === Role::Teacher;
        $this->authorize($isTeacher ? 'teachers.view' : 'staff.view');
        abort_unless(in_array($user->role, [Role::Teacher, Role::Admin, Role::Manager, Role::Operator], true), 404);

        return response()->json(['success' => true, 'data' => [
            'accruals' => $isTeacher ? array_map(fn ($a) => [
                'group_id' => $a['group']->id, 'group' => $a['group']->name, 'accrued' => $a['accrued_by_attendance'], 'paid' => $a['paid'], 'remaining' => $a['remaining'],
            ], $payroll->teacherAccruals($user)) : [],
            'payouts' => Payout::where('recipient_id', $user->id)->latest('id')->limit(50)->get()
                ->map(fn ($p) => ['id' => $p->id, 'amount' => $p->amount, 'method' => $p->method->label(), 'description' => $p->description, 'created_at' => $p->created_at->toIso8601String()]),
        ]]);
    }

    private function row(User $u): array
    {
        return [
            'id' => $u->id, 'name' => $u->name, 'role' => $u->role->value, 'role_label' => $u->role->label(),
            'phone' => $u->phone, 'email' => $u->email, 'status' => $u->status->value, 'branch' => $u->branch?->name,
        ];
    }
}
