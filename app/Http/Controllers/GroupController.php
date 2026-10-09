<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\Schedule;
use App\Http\Requests\GroupRequest;
use App\Http\Requests\GroupUpdateRequest;
use App\Models\Course;
use App\Models\Group;
use App\Models\LessonTime;
use App\Models\PricePlan;
use App\Models\Room;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\GroupService;
use App\Support\SafeInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function __construct(private GroupService $groups) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Group::class);

        $user = $request->user();
        $status = SafeInput::string($request->input('status'), 'current', 20);

        $groups = Group::with(['course', 'teacher', 'room', 'lessonTime'])
            ->withCount(['activeMembers as students_count'])
            ->when($user->role === Role::Teacher, fn ($q) => $q->where('teacher_id', $user->id))
            ->status($status)
            ->when($request->filled('teacher_id'), fn ($q) => $q->where('teacher_id', $request->integer('teacher_id')))
            ->when(SafeInput::string($request->input('q')), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->orderByDesc('starts_on')
            ->paginate(20)
            ->withQueryString();

        return view('groups.index', [
            'groups' => $groups,
            'teachers' => $user->role === Role::Teacher ? collect() : User::visibleToContext()->ofRole(Role::Teacher)->orderBy('name')->get(['id', 'name']),
            'status' => $status,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Group::class);

        return view('groups.form', $this->formData());
    }

    public function store(GroupRequest $request): RedirectResponse
    {
        $this->authorize('create', Group::class);

        $group = $this->groups->create($request->validated(), $request->user());

        return redirect()->route('groups.show', $group)->with('success', "«{$group->name}» guruhi yaratildi ({$group->lesson_count} ta dars).");
    }

    public function show(Group $group, AttendanceService $attendance)
    {
        $this->authorize('view', $group);

        $group->load(['course', 'teacher', 'room', 'lessonTime', 'creator', 'nextGroup']);
        $isTeacher = auth()->user()->role === Role::Teacher;

        return view('groups.show', [
            'group' => $group,
            'members' => $group->activeMembers()->with('student')->get()->sortBy('student.name')->values(),
            'matrix' => $attendance->matrix($group),
            'lessonToday' => $attendance->isLessonDay($group, today()),
            'takenToday' => $attendance->isTaken($group, today()),
            'isTeacher' => $isTeacher,
            'days' => $group->days()->get(),
            'continueData' => $isTeacher || ! auth()->user()->can('groups.create') ? null : $this->formData(),
            // v10 (2-band): admin/sAdmin qarzi bor o'quvchini ham keyingi guruhga o'tkaza oladi (istisno).
            'canEnrollDebtor' => auth()->user()->can('groups.enroll_debtor'),
        ]);
    }

    public function edit(Group $group)
    {
        $this->authorize('update', $group);

        $locked = $this->groups->lockedDays($group);

        return view('groups.edit', [
            'group' => $group,
            'lockedCount' => $locked->count(),
            'groupStatus' => $group->status,
            'lockedUntil' => $locked->last()?->date,
            'membersCount' => $group->activeMembers()->count(),
            'canChangePrice' => auth()->user()->can('groups.change_price'),
        ] + $this->formData());
    }

    public function update(GroupUpdateRequest $request, Group $group): RedirectResponse
    {
        $this->authorize('update', $group);

        $data = $request->validated();

        // Narx rejasini faqat `groups.change_price` ruxsati bor foydalanuvchi o'zgartira oladi
        if (! $request->user()->can('groups.change_price')) {
            unset($data['price_plan_id'], $data['confirm_price_change']);
        }

        $this->groups->update($group, $data, $request->user());

        return redirect()->route('groups.show', $group)->with('success', "Guruh ma'lumotlari yangilandi.");
    }

    /** Guruhni davom ettirish: yangi guruh ochiladi va tanlangan o'quvchilar unga o'tkaziladi. */
    public function continue(GroupRequest $request, Group $group): RedirectResponse
    {
        $this->authorize('create', Group::class);
        $this->authorize('manageMembers', $group);

        $new = $this->groups->continueGroup($group, $request->validated(), $request->input('students', []), $request->user());

        return redirect()->route('groups.show', $new)->with('success', "Guruh davom ettirildi: «{$new->name}».");
    }

    /** v13 (1-bosqich): boshlanmagan guruhni o'chirish - sabab va tekshiruv, keyin «Tekshiring» sahifasi (parol bilan). */
    public function archiveInitiate(Request $request, Group $group, \App\Services\ConfirmationService $confirm): RedirectResponse
    {
        $this->authorize('delete', $group);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'Sabab']);

        $this->groups->assertArchivable($group);

        $token = $confirm->stash(
            'group.archive',
            ['group_id' => $group->id, 'reason' => $data['reason']],
            "Guruhni o'chirish (arxivlash)",
            [
                ['Guruh', $group->name],
                ['Kurs', $group->course?->name ?? '—'],
                ['Boshlanish sanasi', $group->starts_on->format('d.m.Y')],
                ['Darslar soni', (string) $group->lesson_count],
                ['Sabab', $data['reason']],
            ],
            route('groups.show', $group),
            null,
            "Guruh ro'yxatdan olib tashlanadi va dars kunlari bo'shatiladi. Tasdiqlash uchun parolingizni kiriting.",
        );

        return redirect()->route('confirm.show', $token);
    }

    private function formData(): array
    {
        return [
            'courses' => Course::active()->orderBy('name')->get(),
            'teachers' => User::visibleToContext()->ofRole(Role::Teacher)->where('status', 'active')->orderBy('name')->get(),
            'rooms' => Room::active()->orderBy('name')->get(),
            'times' => LessonTime::active()->orderBy('starts_at')->get(),
            'plans' => PricePlan::active()->orderBy('name')->get(),
            'schedules' => Schedule::cases(),
        ];
    }
}
