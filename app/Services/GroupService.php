<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\Schedule;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Group;
use App\Models\GroupDay;
use App\Models\LessonTime;
use App\Models\PricePlan;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupService
{
    public function __construct(
        private ScheduleService $schedule,
        private EnrollmentService $enrollment,
    ) {}

    /**
     * Guruh va uning dars kunlarini yaratadi.
     *
     * @param  array{name:string,course_id:int,teacher_id:int,room_id:int,lesson_time_id:int,price_plan_id:int,schedule:string,starts_on:string,lesson_count:int,teacher_rate?:int,teacher_bonus_rate?:int}  $data
     */
    public function create(array $data, User $actor): Group
    {
        $course = Course::active()->findOrFail($data['course_id']);
        $room = Room::active()->findOrFail($data['room_id']);
        $time = LessonTime::active()->findOrFail($data['lesson_time_id']);
        $plan = PricePlan::active()->findOrFail($data['price_plan_id']);
        $teacher = $this->teacher((int) $data['teacher_id']);
        $schedule = Schedule::from($data['schedule']);

        $dates = $this->schedule->lessonDates($data['starts_on'], (int) $data['lesson_count'], $schedule);

        $this->assertNoConflicts($dates, $room->id, $time->id, $teacher->id);

        return DB::transaction(function () use ($data, $course, $room, $time, $plan, $teacher, $schedule, $dates, $actor) {
            $group = Group::create([
                'course_id' => $course->id,
                'room_id' => $room->id,
                'lesson_time_id' => $time->id,
                'teacher_id' => $teacher->id,
                'created_by' => $actor->id,
                'name' => mb_strtoupper(trim($data['name'])),
                'schedule' => $schedule,
                'price' => $plan->amount,
                'early_discount' => $plan->early_discount,
                'max_discount' => $plan->max_discount,
                'lesson_count' => count($dates),
                'starts_on' => $dates[0],
                'ends_on' => end($dates),
                'teacher_rate' => (int) ($data['teacher_rate'] ?? 0),
                'teacher_bonus_rate' => (int) ($data['teacher_bonus_rate'] ?? 0),
            ]);

            GroupDay::insert(array_map(fn ($date) => [
                'group_id' => $group->id,
                'room_id' => $room->id,
                'lesson_time_id' => $time->id,
                'teacher_id' => $teacher->id,
                'date' => $date,
            ], $dates));

            AuditLog::record('group.created', $group, "Yangi guruh: {$group->name}");

            return $group;
        });
    }

    /** Nom, kurs, o'qituvchi va o'qituvchi stavkalarini yangilaydi. */
    public function update(Group $group, array $data): Group
    {
        $teacherId = (int) $data['teacher_id'];

        if ($teacherId !== $group->teacher_id) {
            $teacher = $this->teacher($teacherId);
            $futureDates = $group->days()->whereDate('date', '>=', today())->pluck('date')->map->toDateString()->all();
            $this->assertNoConflicts($futureDates, null, $group->lesson_time_id, $teacher->id, ignoreGroupId: $group->id);
        }

        DB::transaction(function () use ($group, $data, $teacherId) {
            $group->update([
                'name' => mb_strtoupper(trim($data['name'])),
                'course_id' => Course::findOrFail($data['course_id'])->id,
                'teacher_id' => $teacherId,
                'teacher_rate' => (int) ($data['teacher_rate'] ?? 0),
                'teacher_bonus_rate' => (int) ($data['teacher_bonus_rate'] ?? 0),
            ]);

            if ($group->wasChanged('teacher_id')) {
                GroupDay::where('group_id', $group->id)->whereDate('date', '>=', today())->update(['teacher_id' => $teacherId]);
            }

            AuditLog::record('group.updated', $group, "Guruh yangilandi: {$group->name}");
        });

        return $group;
    }

    /**
     * Guruhni davom ettiradi: yangi guruh yaratadi va tanlangan o'quvchilarni unga qo'shadi.
     *
     * @param  array<int,int>  $studentIds
     */
    public function continueGroup(Group $old, array $data, array $studentIds, User $actor): Group
    {
        $members = $old->activeMembers()->with('student')->whereIn('student_id', $studentIds)->get();

        // Qarzi bor o'quvchilar odatda keyingi guruhga o'tkazilmaydi. ISTISNO (v10): admin/sAdmin
        // (`groups.enroll_debtor`) ularni ham o'tkaza oladi — xuddi qo'lda qo'shishdagi kabi.
        $canEnrollDebtor = $actor->can('groups.enroll_debtor');
        $debtors = $members->filter(fn ($m) => $m->student->balance < 0);
        if ($debtors->isNotEmpty() && ! $canEnrollDebtor) {
            $list = $debtors->map(fn ($m) => $m->student->name.' ('.\App\Support\Format::money($m->student->balance).')')->implode(', ');

            throw ValidationException::withMessages(['students' => "Qarzi bor o'quvchilarni keyingi guruhga qo'shib bo'lmaydi: {$list}. Avval to'lov qabul qiling yoki ularni belgidan olib tashlang."]);
        }

        return DB::transaction(function () use ($old, $data, $members, $actor) {
            $new = $this->create($data, $actor);
            $old->update(['next_group_id' => $new->id]);

            foreach ($members as $member) {
                $debtor = $member->student->balance < 0;
                $this->enrollment->enroll($new, $member->student, "Davom: {$old->name}".($debtor ? ' (qarzdor, istisno)' : ''), $actor, $debtor);
            }

            return $new;
        });
    }

    private function teacher(int $id): User
    {
        return User::visibleToContext()->ofRole(Role::Teacher)->where('status', 'active')->findOrFail($id);
    }

    /**
     * Xona yoki o'qituvchi shu vaqtda band bo'lsa, tushunarli xato qaytaradi.
     *
     * @param  array<int,string>  $dates
     */
    private function assertNoConflicts(array $dates, ?int $roomId, int $timeId, int $teacherId, ?int $ignoreGroupId = null): void
    {
        $conflict = GroupDay::query()
            ->with('group:id,name')
            ->whereIn('date', $dates)
            ->where('lesson_time_id', $timeId)
            ->when($ignoreGroupId, fn ($q) => $q->where('group_id', '!=', $ignoreGroupId))
            ->where(fn ($q) => $q->where('teacher_id', $teacherId)->when($roomId, fn ($q2) => $q2->orWhere('room_id', $roomId)))
            ->orderBy('date')
            ->first();

        if (! $conflict) {
            return;
        }

        $what = $conflict->teacher_id === $teacherId ? "O'qituvchi" : 'Xona';

        throw ValidationException::withMessages([
            'lesson_time_id' => "{$what} {$conflict->date->format('d.m.Y')} kuni shu vaqtda band ({$conflict->group->name} guruhi).",
        ]);
    }
}
