<?php

namespace App\Http\Controllers;

use App\Support\Viz;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Services\AttendanceService;
use App\Support\SafeInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance) {}

    /** Bugun dars bo'ladigan guruhlar va davomad holati. */
    public function today(Request $request)
    {
        $this->authorize('attendance.view');

        $user = $request->user();

        $groups = Group::with(['course', 'teacher', 'lessonTime', 'room'])
            ->withCount(['activeMembers as students_count'])
            ->havingLessonOn(today())
            ->when($user->role === Role::Teacher, fn ($q) => $q->where('teacher_id', $user->id))
            ->orderBy('lesson_time_id')
            ->get();

        $stats = $this->attendance->daily(today()->toDateString(), $user->role === Role::Teacher ? $user->id : null);

        return view('attendance.today', [
            'groups' => $groups,
            'byGroup' => collect($stats['rows'])->keyBy(fn ($r) => $r['group']->id),
        ]);
    }

    public function take(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('takeAttendance', $group);

        $data = $request->validate([
            'present' => ['nullable', 'array'],
            'present.*' => ['integer'],
        ]);

        $this->attendance->takeToday($group, $data['present'] ?? [], $request->user());

        return back()->with('success', 'Davomad saqlandi.');
    }

    /** v8: o'tgan kunni tuzatish sahifasi - sana tanlansa, o'sha kunning holati (bor bo'lsa) ko'rsatiladi. */
    public function pastForm(Request $request, Group $group)
    {
        $this->authorize('editPastAttendance', $group);

        $date = SafeInput::date($request->input('date'), today()->subDay()->toDateString());
        $matrix = $this->attendance->matrix($group);

        $members = GroupStudent::with('student:id,name')->where('group_id', $group->id)->where('is_active', true)->orderBy('id')->get();
        $existing = Attendance::where('group_id', $group->id)->where('date', $date)->pluck('is_present', 'student_id');

        return view('groups.attendance-past', [
            'group' => $group,
            'date' => $date,
            'isPast' => $date < today()->toDateString(),
            'isLessonDay' => $this->attendance->isLessonDay($group, $date),
            'wasTaken' => $existing->isNotEmpty(),
            'members' => $members,
            'existing' => $existing,
            'pastDays' => collect($matrix['days'])->whereIn('state', ['held', 'missed'])->reverse()->values(),
        ]);
    }

    public function editPast(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('editPastAttendance', $group);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'present' => ['nullable', 'array'],
            'present.*' => ['integer'],
        ]);

        $this->attendance->editPast($group, $data['date'], $data['present'] ?? [], $request->user());

        return redirect()->route('attendance.past.show', ['group' => $group, 'date' => $data['date']])
            ->with('success', "{$data['date']} kunidagi davomad saqlandi.");
    }

    public function stats(Request $request)
    {
        $this->authorize('attendance.stats');

        $date = SafeInput::date($request->input('date'), today()->toDateString());
        $month = SafeInput::month($request->input('month'), today()->format('Y-m'));

        $daily = $this->attendance->daily($date);
        $monthly = $this->attendance->monthly($month);

        return view('attendance.stats', [
            'date' => $date,
            'month' => $month,
            'daily' => $daily,
            'monthly' => $monthly,
            'charts' => $this->charts($daily, $monthly),
        ]);
    }

    /**
     * Davomad grafiklari: keldi = yashil, kelmadi = qizil (holat ranglari), qolganlari toifa ranglari.
     *
     * @return array<string, mixed>
     */
    private function charts(array $daily, array $monthly): array
    {
        $c = [];
        $t = $daily['totals'];
        $c['day_split'] = Viz::donut(['Keldi', 'Kelmadi'], [$t['present'], $t['absent']], 'int',
            ['value' => $t['rate'] === null ? '—' : $t['rate'].'%', 'label' => 'davomad'], ['good', 'critical'], "O'quvchilar", 'Holat');

        $rows = collect($daily['rows'])->filter(fn ($r) => $r['rate'] !== null)->sortByDesc('rate')->values();
        $c['day_groups'] = Viz::bar($rows->map(fn ($r) => $r['group']->name)->all(),
            [['label' => 'Davomad', 'data' => $rows->pluck('rate')->all(), 'slot' => 1]], 'percent', nameColumn: 'Guruh');
        $c['day_groups_h'] = max(160, 30 * $rows->count() + 50);

        $series = $monthly['series'];
        $labels = array_map(fn ($p) => substr($p['date'], 8, 2).'.'.substr($p['date'], 5, 2), $series);
        $c['month_rate'] = Viz::line($labels, [['label' => 'Davomad', 'data' => array_column($series, 'rate'), 'slot' => 1]], 'percent', true, 'Kun');
        $c['month_split'] = Viz::column($labels, [
            ['label' => 'Keldi', 'data' => array_column($series, 'present'), 'color' => 'good'],
            ['label' => 'Kelmadi', 'data' => array_map(fn ($p) => $p['total'] - $p['present'], $series), 'color' => 'critical'],
        ], 'int', stacked: true, nameColumn: 'Kun');

        $g = collect($monthly['rows'])->filter(fn ($r) => $r['rate'] !== null)->sortByDesc('rate')->values();
        $c['month_groups'] = Viz::bar($g->map(fn ($r) => $r['group']->name)->all(),
            [['label' => 'Davomad', 'data' => $g->pluck('rate')->all(), 'slot' => 1]], 'percent', nameColumn: 'Guruh');
        $c['month_groups_h'] = max(160, 30 * $g->count() + 50);

        $h = collect($monthly['rows']);
        $c['held'] = Viz::bar($h->map(fn ($r) => $r['group']->name)->all(), [
            ['label' => 'Rejalangan kunlar', 'data' => $h->pluck('scheduled')->all(), 'slot' => 5],
            ['label' => 'Davomad olingan', 'data' => $h->pluck('held')->all(), 'slot' => 1],
        ], 'int', nameColumn: 'Guruh');
        $c['held_h'] = max(200, 46 * $h->count() + 60);

        $w = $monthly['worst'];
        $c['worst'] = Viz::bar(array_column($w, 'name'), [['label' => 'Davomad', 'data' => array_column($w, 'rate'), 'color' => 'critical']], 'percent', nameColumn: "O'quvchi");
        $c['worst_h'] = max(160, 30 * count($w) + 50);

        return $c;
    }
}
