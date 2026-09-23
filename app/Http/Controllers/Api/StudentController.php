<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use App\Services\PaymentService;
use App\Support\BranchContext;
use App\Support\SafeInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Xodimlar uchun (kassir/menejer/admin) o'quvchilar bilan ishlash. */
class StudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('students.view'), 403);

        $students = User::visibleToContext()->where('role', Role::Student)->whereNull('archived_at')
            ->when($request->input('debtors'), fn ($q) => $q->where('balance', '<', 0))
            ->search(SafeInput::string($request->input('q')))->orderBy('name')->paginate(min(50, max(5, $request->integer('per_page', 20))));

        return response()->json(['success' => true, 'data' => $students->getCollection()->map(fn ($s) => $this->row($s)), 'meta' => [
            'page' => $students->currentPage(), 'last_page' => $students->lastPage(), 'total' => $students->total(),
        ]]);
    }

    public function show(Request $request, User $student): JsonResponse
    {
        abort_unless($request->user()->can('students.view'), 403);

        $s = $student;
        $groups = Group::whereIn('id', $s->memberships()->where('is_active', true)->select('group_id'))->with(['course:id,name', 'teacher:id,name'])->get()
            ->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'course' => $g->course->name, 'teacher' => $g->teacher->name, 'price' => $g->price, 'status' => $g->status]);

        return response()->json(['success' => true, 'data' => $this->row($s) + ['groups' => $groups]]);
    }

    /** To'lov qabul qilish (kassir uchun). */
    public function pay(Request $request, User $student, PaymentService $payments): JsonResponse
    {
        abort_unless($request->user()->can('payments.create'), 403);

        $data = $request->validate([
            'cash' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'card' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'group_id' => ['nullable', BranchContext::exists('groups')],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], ['cash' => 'Naqt', 'card' => 'Plastik', 'group_id' => 'Guruh', 'description' => 'Izoh']);

        $s = $student;

        $created = $payments->receive($s, ['cash' => (int) ($data['cash'] ?? 0), 'card' => (int) ($data['card'] ?? 0)],
            isset($data['group_id']) ? Group::findOrFail($data['group_id']) : null, $data['description'] ?? null, $request->user());

        return response()->json([
            'success' => true, 'message' => "To'lov qabul qilindi.",
            'data' => ['balance' => $s->fresh()->balance, 'discount' => (int) collect($created)->whereIn('type', ['discount', 'campaign_bonus'])->sum('amount')],
        ]);
    }

    private function row(User $s): array
    {
        return ['id' => $s->id, 'name' => $s->name, 'phone' => $s->phone, 'phone2' => $s->phone2, 'balance' => $s->balance, 'debt' => max(0, -$s->balance)];
    }
}
