<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Group;
use App\Models\GroupDay;
use App\Models\GroupStudent;
use App\Models\SmsMessage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(private SmsNotifier $notifier) {}

    public function isLessonDay(Group $group, $date): bool
    {
        return GroupDay::where('group_id', $group->id)->whereDate('date', $date)->exists();
    }

    public function isTaken(Group $group, $date): bool
    {
        return AttendanceSession::where('group_id', $group->id)->whereDate('date', $date)->exists();
    }

    /**
     * BUGUNGI davomadni saqlaydi (yoki bugun olingan davomadni tahrirlaydi).
     * Faqat dars kuni bo'lsa ishlaydi; o'tgan kunlar o'zgartirilmaydi.
     *
     * @param  array<int,int|string>  $presentIds  kelgan o'quvchilar ID si
     */
    public function takeToday(Group $group, array $presentIds, User $actor): AttendanceSession
    {
        $today = today()->toDateString();

        if (! $this->isLessonDay($group, $today)) {
            throw ValidationException::withMessages(['attendance' => "Bugun bu guruhda dars kuni emas, davomad olib bo'lmaydi."]);
        }

        $studentIds = GroupStudent::where('group_id', $group->id)->where('is_active', true)->pluck('student_id')->all();

        if ($studentIds === []) {
            throw ValidationException::withMessages(['attendance' => "Guruhda faol o'quvchi yo'q."]);
        }

        $present = array_map('intval', $presentIds);

        $session = DB::transaction(function () use ($group, $studentIds, $present, $actor, $today) {
            $session = AttendanceSession::updateOrCreate(
                ['group_id' => $group->id, 'date' => $today],
                ['branch_id' => $group->branch_id, 'taken_by' => $actor->id],
            );

            foreach ($studentIds as $studentId) {
                Attendance::updateOrCreate(
                    ['group_id' => $group->id, 'student_id' => $studentId, 'date' => $today],
                    ['branch_id' => $group->branch_id, 'attendance_session_id' => $session->id, 'is_present' => in_array($studentId, $present, true)],
                );
            }

            return $session;
        });

        $this->notifyAbsentees($group, $studentIds, $present);

        return $session;
    }

    /**
     * v8 B1: davomad olingandan keyin kelmagan o'quvchilarning ota-ona raqamiga
     * (`phone2` to'ldirilgan bo'lsa) avtomatik SMS - filialda yoqilgan bo'lsagina,
     * har bir o'quvchiga kuniga bir marta (davomad shu kun ichida qayta saqlansa ham).
     *
     * @param  array<int,int>  $studentIds  guruhning faol o'quvchilari
     * @param  array<int,int>  $present  kelgan o'quvchilar
     */
    private function notifyAbsentees(Group $group, array $studentIds, array $present): void
    {
        $branch = Branch::find($group->branch_id);

        if (! $branch || ! $branch->sms_auto_absent) {
            return;
        }

        $absentIds = array_diff($studentIds, $present);

        if ($absentIds === []) {
            return;
        }

        $students = User::whereIn('id', $absentIds)->whereNotNull('phone2')->where('phone2', '!=', '')->get();

        foreach ($students as $student) {
            $already = SmsMessage::withoutGlobalScopes()->where('recipient_id', $student->id)
                ->where('template_key', 'absence_notice')->whereDate('created_at', today())->exists();

            if (! $already) {
                $this->notifier->notify('absence_notice', $student, ['group' => $group->name], $student->phone2);
            }
        }
    }

    /**
     * v8: O'TGAN kun uchun davomadni yaratadi yoki tuzatadi (`attendance.edit_past`, faqat sAdmin/admin).
     * `takeToday()`dan farqi: bugundan oldingi istalgan dars kuni uchun ishlaydi - hatto o'sha kuni
     * umuman davomad olinmagan bo'lsa ham (o'qituvchi unutgan holatni tuzatish uchun). Bugungi yoki
     * kelajakdagi sana uchun ishlamaydi (bugungi kun uchun `takeToday()` ishlatiladi). Kelmaganlar uchun
     * SMS YUBORILMAYDI: shablon matni "bugun" deb yozilgan, orqaga sanaladigan kun uchun noto'g'ri
     * bo'lardi. Amal audit jurnaliga yoziladi.
     *
     * @param  array<int,int|string>  $presentIds  kelgan o'quvchilar ID si
     */
    public function editPast(Group $group, string $date, array $presentIds, User $actor): AttendanceSession
    {
        $day = CarbonImmutable::parse($date)->toDateString();

        if ($day >= today()->toDateString()) {
            throw ValidationException::withMessages(['date' => "Bu bo'lim faqat O'TGAN kunlar uchun. Bugungi kun uchun oddiy davomad sahifasidan foydalaning."]);
        }

        if (! $this->isLessonDay($group, $day)) {
            throw ValidationException::withMessages(['date' => "Bu kuni guruhda dars bo'lmagan."]);
        }

        $studentIds = GroupStudent::where('group_id', $group->id)->where('is_active', true)->pluck('student_id')->all();

        if ($studentIds === []) {
            throw ValidationException::withMessages(['attendance' => "Guruhda faol o'quvchi yo'q."]);
        }

        $present = array_map('intval', $presentIds);

        $session = DB::transaction(function () use ($group, $studentIds, $present, $actor, $day) {
            $session = AttendanceSession::updateOrCreate(
                ['group_id' => $group->id, 'date' => $day],
                ['branch_id' => $group->branch_id, 'taken_by' => $actor->id],
            );

            foreach ($studentIds as $studentId) {
                Attendance::updateOrCreate(
                    ['group_id' => $group->id, 'student_id' => $studentId, 'date' => $day],
                    ['branch_id' => $group->branch_id, 'attendance_session_id' => $session->id, 'is_present' => in_array($studentId, $present, true)],
                );
            }

            return $session;
        });

        AuditLog::record('attendance.edited_past', $group, "«{$group->name}» guruhida {$day} kunidagi davomad tuzatildi (".count($present)."/".count($studentIds)." keldi)");

        return $session;
    }

    /**
     * Guruh davomad jadvali: kunlar holati va har bir o'quvchi uchun kelgan/kelmagan.
     *
     * @return array{days: array<int, array{date:string,state:string}>, rows: array<int, array<string,mixed>>}
     */
    public function matrix(Group $group): array
    {
        $today = today()->toDateString();

        $sessions = AttendanceSession::where('group_id', $group->id)->get(['id', 'date'])
            ->keyBy(fn ($s) => $s->date->toDateString());

        $days = GroupDay::where('group_id', $group->id)->orderBy('date')->get(['date'])
            ->map(function ($day) use ($sessions, $today) {
                $date = $day->date->toDateString();
                $state = match (true) {
                    $date > $today => 'upcoming',
                    $sessions->has($date) => 'held',
                    $date === $today => 'today',
                    default => 'missed',
                };

                return ['date' => $date, 'state' => $state];
            })->all();

        $records = Attendance::where('group_id', $group->id)->get(['student_id', 'date', 'is_present']);
        $byStudent = [];
        foreach ($records as $r) {
            $byStudent[$r->student_id][$r->date->toDateString()] = (bool) $r->is_present;
        }

        $members = GroupStudent::with('student:id,name')
            ->where('group_id', $group->id)
            ->orderByDesc('is_active')
            ->get()
            ->unique('student_id');

        $rows = [];
        foreach ($members as $member) {
            $cells = $byStudent[$member->student_id] ?? [];

            // Guruhdan chiqqan va yozuvi yo'q o'quvchini ko'rsatmaymiz
            if (! $member->is_active && $cells === []) {
                continue;
            }

            $present = count(array_filter($cells));
            $rows[] = [
                'student' => $member->student,
                'active' => $member->is_active,
                'cells' => $cells,
                'present' => $present,
                'total' => count($cells),
                'rate' => $cells ? round($present * 100 / count($cells)) : null,
            ];
        }

        return ['days' => $days, 'rows' => $rows];
    }

    /**
     * v12: bitta o'quvchi uchun bitta guruhdagi davomat xulosasi - `matrix()` kabi BUTUN
     * guruh matritsasini (barcha o'quvchilar, barcha kunlar, barcha yozuvlar) qurmasdan,
     * to'g'ridan-to'g'ri shu o'quvchining yozuvlarini agregatsiya qiladi. Mobil `/me/attendance`
     * o'quvchi a'zo bo'lgan HAR BIR guruh uchun shuni chaqiradi - shuning uchun har biri
     * yengil (bitta agregat so'rov) bo'lishi kerak, guruh kattaligiga bog'liq bo'lmasdan.
     *
     * @return array{present:int,total:int,rate:?int}|null o'quvchi guruhga (faol yoki tarixiy
     *                                                       yozuvi bilan) tegishli bo'lmasa null
     */
    public function studentSummary(Group $group, int $studentId): ?array
    {
        $membership = GroupStudent::where('group_id', $group->id)->where('student_id', $studentId)
            ->orderByDesc('is_active')->first();

        if (! $membership) {
            return null;
        }

        $agg = Attendance::where('group_id', $group->id)->where('student_id', $studentId)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_present THEN 1 ELSE 0 END) as present')
            ->first();

        $total = (int) ($agg->total ?? 0);
        $present = (int) ($agg->present ?? 0);

        // Guruhdan chiqqan va yozuvi yo'q o'quvchini ko'rsatmaymiz (matrix() bilan bir xil qoida)
        if (! $membership->is_active && $total === 0) {
            return null;
        }

        return [
            'present' => $present,
            'total' => $total,
            'rate' => $total ? (int) round($present * 100 / $total) : null,
        ];
    }

    /**
     * Kunlik statistika: shu kuni dars bo'lgan guruhlar kesimida.
     *
     * @return array{rows: array<int,array<string,mixed>>, totals: array<string,int|float|null>}
     */
    public function daily(string $date, ?int $teacherId = null): array
    {
        $groups = Group::with(['teacher:id,name', 'course:id,name', 'lessonTime'])
            ->havingLessonOn($date)
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->orderBy('name')
            ->get();

        $ids = $groups->pluck('id');
        $sessions = AttendanceSession::whereIn('group_id', $ids)->whereDate('date', $date)->pluck('id', 'group_id');
        $counts = Attendance::whereIn('group_id', $ids)->where('date', $date)
            ->selectRaw('group_id, sum(is_present) as present, count(*) as total')
            ->groupBy('group_id')->get()->keyBy('group_id');

        $rows = [];
        $totals = ['groups' => $groups->count(), 'taken' => 0, 'present' => 0, 'absent' => 0];

        foreach ($groups as $group) {
            $taken = $sessions->has($group->id);
            $present = (int) ($counts[$group->id]->present ?? 0);
            $total = (int) ($counts[$group->id]->total ?? 0);

            $totals['taken'] += $taken ? 1 : 0;
            $totals['present'] += $present;
            $totals['absent'] += $total - $present;

            $rows[] = [
                'group' => $group,
                'taken' => $taken,
                'present' => $present,
                'absent' => $total - $present,
                'total' => $total,
                'rate' => $total ? round($present * 100 / $total) : null,
            ];
        }

        $all = $totals['present'] + $totals['absent'];
        $totals['rate'] = $all ? round($totals['present'] * 100 / $all) : null;

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Oylik statistika: guruhlar, kunlar dinamikasi va eng ko'p qoldirayotgan o'quvchilar.
     */
    public function monthly(string $month, ?int $teacherId = null): array
    {
        $from = CarbonImmutable::parse($month.'-01')->startOfMonth();
        $to = $from->endOfMonth();
        $upTo = $to->gt(today()) ? today() : $to;

        $groups = Group::with(['teacher:id,name', 'course:id,name'])
            ->whereDate('starts_on', '<=', $to)->whereDate('ends_on', '>=', $from)
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->orderBy('name')->get();
        $ids = $groups->pluck('id');

        $scheduled = GroupDay::whereIn('group_id', $ids)
            ->whereBetween('date', [$from->toDateString(), $upTo->toDateString()])
            ->selectRaw('group_id, count(*) as days')->groupBy('group_id')->pluck('days', 'group_id');

        $held = AttendanceSession::whereIn('group_id', $ids)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('group_id, count(*) as days')->groupBy('group_id')->pluck('days', 'group_id');

        $perGroup = Attendance::whereIn('group_id', $ids)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('group_id, sum(is_present) as present, count(*) as total')
            ->groupBy('group_id')->get()->keyBy('group_id');

        $rows = [];
        $sumPresent = $sumTotal = 0;
        foreach ($groups as $group) {
            $present = (int) ($perGroup[$group->id]->present ?? 0);
            $total = (int) ($perGroup[$group->id]->total ?? 0);
            $sumPresent += $present;
            $sumTotal += $total;

            $rows[] = [
                'group' => $group,
                'scheduled' => (int) ($scheduled[$group->id] ?? 0),
                'held' => (int) ($held[$group->id] ?? 0),
                'present' => $present,
                'absent' => $total - $present,
                'rate' => $total ? round($present * 100 / $total) : null,
            ];
        }

        $series = Attendance::whereIn('group_id', $ids)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('date, sum(is_present) as present, count(*) as total')
            ->groupBy('date')->orderBy('date')->get()
            ->map(fn ($r) => [
                'date' => CarbonImmutable::parse($r->date)->toDateString(),
                'rate' => $r->total ? round($r->present * 100 / $r->total) : 0,
                'present' => (int) $r->present,
                'total' => (int) $r->total,
            ])->all();

        $worst = Attendance::whereIn('group_id', $ids)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('student_id, sum(is_present) as present, count(*) as total')
            ->groupBy('student_id')->havingRaw('count(*) >= 3')
            ->orderByRaw('sum(is_present) * 1.0 / count(*) asc')->limit(10)->get();
        $names = User::whereIn('id', $worst->pluck('student_id'))->pluck('name', 'id');

        return [
            'rows' => $rows,
            'series' => $series,
            'worst' => $worst->map(fn ($r) => [
                'name' => $names[$r->student_id] ?? '—',
                'student_id' => $r->student_id,
                'present' => (int) $r->present,
                'total' => (int) $r->total,
                'rate' => round($r->present * 100 / $r->total),
            ])->all(),
            'totals' => [
                'groups' => $groups->count(),
                'present' => $sumPresent,
                'absent' => $sumTotal - $sumPresent,
                'rate' => $sumTotal ? round($sumPresent * 100 / $sumTotal) : null,
            ],
        ];
    }
}
