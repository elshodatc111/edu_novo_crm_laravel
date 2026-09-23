<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** v11: mobil ilovada guruhga yangi o'quvchi biriktirish/chiqarish (veb bilan bir xil servis). */
class GroupStudentController extends Controller
{
    public function __construct(private EnrollmentService $enrollment) {}

    public function store(Request $request, Group $group): JsonResponse
    {
        $this->authorize('manageMembers', $group);

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:500'],
            'allow_debt' => ['nullable', 'boolean'],
        ], [], ['student_id' => "O'quvchi", 'note' => 'Izoh']);

        $student = User::visibleToContext()->where('role', 'student')->findOrFail($data['student_id']);

        $this->enrollment->enroll($group, $student, $data['note'] ?? null, $request->user(), $request->boolean('allow_debt'));

        return response()->json(['success' => true, 'message' => "{$student->name} «{$group->name}» guruhiga qo'shildi."]);
    }

    public function destroy(Request $request, Group $group, User $student): JsonResponse
    {
        $this->authorize('manageMembers', $group);

        $data = $request->validate([
            'fine' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['fine' => 'Jarima', 'note' => 'Sabab']);

        $this->enrollment->remove($group, $student, (int) ($data['fine'] ?? 0), $data['note'] ?? null, $request->user());

        return response()->json(['success' => true, 'message' => "{$student->name} guruhdan chiqarildi."]);
    }
}
