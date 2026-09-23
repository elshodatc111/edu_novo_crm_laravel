<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentRequest;
use App\Models\Group;
use App\Models\LeadSource;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\StudentService;
use App\Support\SafeInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct(private StudentService $students) {}

    public function index(Request $request)
    {
        $this->authorize('students.view');

        $students = User::visibleToContext()
            ->with('branch')
            ->withCount(['memberships as active_groups_count' => fn ($q) => $q->where('is_active', true)])
            ->where('role', 'student')
            ->when(SafeInput::string($request->input('status'), 'active') === 'archived', fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->when(SafeInput::string($request->input('status')) === 'debt', fn ($q) => $q->where('balance', '<', 0))
            ->when($request->filled('group_id'), fn ($q) => $q->whereHas('memberships', fn ($m) => $m->where('group_id', $request->integer('group_id'))->where('is_active', true)))
            ->search(SafeInput::string($request->input('q')))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('students.index', [
            'students' => $students,
            'groups' => Group::status('current')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Guruhga qo'shish uchun tezkor qidiruv (JSON). */
    public function search(Request $request)
    {
        $this->authorize('groups.members');

        $term = SafeInput::string($request->input('q')) ?? '';

        $found = strlen($term) < 2 ? collect() : User::visibleToContext()
            ->where('role', 'student')->whereNull('archived_at')
            ->search($term)->orderBy('name')->limit(10)->get(['id', 'name', 'phone', 'balance']);

        return response()->json($found);
    }

    public function create()
    {
        $this->authorize('students.create');

        return view('students.form', ['student' => new User, 'sources' => LeadSource::active()->orderBy('name')->get()]);
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        $this->authorize('students.create');

        [$student, $password] = $this->students->create($request->validated(), $request->user());

        return redirect()->route('students.show', $student)
            ->with('success', "{$student->name} qo'shildi.")
            ->with('credentials', ['login' => $student->username, 'password' => $password]);
    }

    public function show(User $student)
    {
        $this->authorize('students.view');

        $student->load('leadSource');

        return view('students.show', [
            'student' => $student,
            'memberships' => $student->memberships()->with(['group.teacher', 'addedBy', 'removedBy'])->orderByDesc('is_active')->orderByDesc('id')->get(),
            'notes' => auth()->user()->can('students.notes') ? $student->notes()->with('user')->latest()->get() : collect(),
            'timeline' => app(\App\Services\StudentTimeline::class)->for($student),
            'campaigns' => auth()->user()->can('payments.create') ? \App\Models\DiscountCampaign::running()->orderBy('name')->get() : collect(),
            'availableGroups' => auth()->user()->can('groups.members')
                ? Group::whereDate('ends_on', '>=', today()->subDays(EnrollmentService::LATE_ENROLL_DAYS))
                    ->whereNotIn('id', $student->memberships()->where('is_active', true)->select('group_id'))
                    ->orderBy('name')->get(['id', 'name', 'price', 'starts_on', 'ends_on'])
                : collect(),
        ]);
    }

    public function edit(User $student)
    {
        $this->authorize('students.update');

        return view('students.form', ['student' => $student, 'sources' => LeadSource::active()->orderBy('name')->get()]);
    }

    public function update(StudentRequest $request, User $student): RedirectResponse
    {
        $this->authorize('students.update');

        $this->students->update($student, $request->validated());

        return redirect()->route('students.show', $student)->with('success', "Ma'lumotlar saqlandi.");
    }

    public function resetPassword(User $student): RedirectResponse
    {
        $this->authorize('students.update');

        $password = $this->students->resetPassword($student);

        return back()->with('success', 'Yangi parol yaratildi.')
            ->with('credentials', ['login' => $student->username, 'password' => $password]);
    }

    public function archive(User $student): RedirectResponse
    {
        $this->authorize('students.archive');

        $this->students->archive($student);

        return redirect()->route('students.index')->with('success', "{$student->name} arxivga o'tkazildi.");
    }

    public function restore(User $student): RedirectResponse
    {
        $this->authorize('students.archive');

        $this->students->restore($student);

        return back()->with('success', 'Arxivdan qaytarildi.');
    }
}
