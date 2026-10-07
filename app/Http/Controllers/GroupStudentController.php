<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\User;
use App\Services\ContractService;
use App\Services\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GroupStudentController extends Controller
{
    public function __construct(private EnrollmentService $enrollment) {}

    public function store(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('manageMembers', $group);

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:500'],
            'allow_debt' => ['nullable', 'boolean'],
        ], [], ['student_id' => "O'quvchi", 'note' => 'Izoh']);

        $student = User::visibleToContext()->where('role', 'student')->findOrFail($data['student_id']);

        $this->enrollment->enroll($group, $student, $data['note'] ?? null, $request->user(), $request->boolean('allow_debt'));

        return back()->with('success', "{$student->name} «{$group->name}» guruhiga qo'shildi.");
    }

    public function destroy(Request $request, Group $group, User $student): RedirectResponse
    {
        $this->authorize('manageMembers', $group);

        $data = $request->validate([
            'fine' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['fine' => 'Jarima', 'note' => 'Sabab']);

        $this->enrollment->remove($group, $student, (int) ($data['fine'] ?? 0), $data['note'] ?? null, $request->user());

        return back()->with('success', "{$student->name} guruhdan chiqarildi.");
    }

    /** v8 B2: shartnoma - A5 formatda chop etish uchun. Eng so'nggi (faol, bo'lmasa - oxirgi) a'zolik yozuvi olinadi. */
    public function contract(Group $group, User $student, ContractService $contracts)
    {
        $this->authorize('manageMembers', $group);

        $enrollment = GroupStudent::where('group_id', $group->id)->where('student_id', $student->id)
            ->orderByDesc('is_active')->orderByDesc('id')->firstOrFail();

        return view('enrollments.contract', [
            'enrollment' => $enrollment,
            'text' => $text = $contracts->render($enrollment),
            'html' => ContractService::toHtml($text),
        ]);
    }
}
