<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\Schedule;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\BalanceTransaction;
use App\Models\Course;
use App\Models\Group;
use App\Models\GroupDay;
use App\Models\LessonTime;
use App\Models\PricePlan;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupService
{
    public function __construct(
        private ScheduleService $schedule,
        private EnrollmentService $enrollment,
        private BalanceService $balance,
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

    /**
     * Guruhni yangilaydi: nom, kurs, o'qituvchi, stavkalar va (v13) xona, dars vaqti, dars kunlari,
     * boshlanish sanasi, darslar soni va narx rejasi.
     *
     * Jadval qoidalari:
     *  - O'TGAN darslar (bugundan oldingi) va davomad OLINGAN darslar o'zgarmaydi - ular «qulflangan»;
     *  - o'zgarish faqat kelgusi, davomad olinmagan darslarni qayta tuzadi (yangi xona/vaqt/kunlar bilan);
     *  - dars boshlangan bo'lsa, boshlanish sanasini o'zgartirib bo'lmaydi; darslar soni qulflanganlardan kam bo'lmaydi;
     *  - boshlanmagan guruhda boshlanish sanasi faqat bugun yoki keyingi kunlarga ko'chiriladi;
     *  - YAKUNLANGAN guruhda (tugash sanasi o'tgan) xona, vaqt, kunlar, boshlanish sanasi va darslar soni o'zgarmaydi;
     *  - yangi sanalar uchun xona va o'qituvchi bandligi tekshiriladi (guruhning o'z darslari hisobga olinmaydi).
     *
     * Narx qoidasi: narx rejasi o'zgarsa, guruhning FAOL o'quvchilari balansiga farq servis (BalanceService) orqali
     * jurnalga yozib hisoblanadi (qimmatlashsa yechiladi, arzonlashsa qaytariladi) - guruhdan chiqarishda qaytariladigan
     * summa har doim joriy narxga teng bo'lishi uchun. Faol o'quvchilar bo'lsa, tasdiq (`confirm_price_change`) shart.
     */
    public function update(Group $group, array $data, ?User $actor = null): Group
    {
        $actor ??= auth()->user();
        $teacherId = (int) $data['teacher_id'];
        $teacherChanged = $teacherId !== (int) $group->teacher_id;
        $teacher = $teacherChanged ? $this->teacher($teacherId) : null;

        $roomId = (int) ($data['room_id'] ?? $group->room_id);
        $timeId = (int) ($data['lesson_time_id'] ?? $group->lesson_time_id);
        $schedule = ! empty($data['schedule']) ? Schedule::from($data['schedule']) : $group->schedule;
        $currentStart = $group->starts_on->toDateString();
        $startsOn = ! empty($data['starts_on']) ? CarbonImmutable::parse($data['starts_on'])->toDateString() : $currentStart;
        $count = (int) ($data['lesson_count'] ?? $group->lesson_count);

        if ($roomId !== (int) $group->room_id) {
            Room::active()->findOrFail($roomId);
        }
        if ($timeId !== (int) $group->lesson_time_id) {
            LessonTime::active()->findOrFail($timeId);
        }

        $scheduleChanged = $roomId !== (int) $group->room_id || $timeId !== (int) $group->lesson_time_id
            || $schedule !== $group->schedule || $startsOn !== $currentStart || $count !== (int) $group->lesson_count;

        // ---- Yakunlangan guruh: jadval maydonlari to'liq qulflangan (v13.1) ----
        if ($scheduleChanged && $group->status === Group::FINISHED) {
            $labels = [
                'room_id' => ['Xona', $roomId !== (int) $group->room_id],
                'lesson_time_id' => ['Dars vaqti', $timeId !== (int) $group->lesson_time_id],
                'schedule' => ['Dars kunlari', $schedule !== $group->schedule],
                'starts_on' => ['Boshlanish sanasi', $startsOn !== $currentStart],
                'lesson_count' => ['Darslar soni', $count !== (int) $group->lesson_count],
            ];
            $errors = [];
            foreach ($labels as $field => [$label, $changed]) {
                if ($changed) {
                    $errors[$field] = "Guruh yakunlangan: «{$label}» o'zgartirilmaydi.";
                }
            }

            throw ValidationException::withMessages($errors);
        }

        // ---- Jadval: yangi kelgusi sanalar ----
        $locked = $this->lockedDays($group);
        $newDates = [];
        if ($scheduleChanged) {
            $k = $locked->count();

            if ($k > 0 && $startsOn !== $currentStart) {
                throw ValidationException::withMessages(['starts_on' => "Dars allaqachon boshlangan ({$k} ta dars o'tgan yoki davomad olingan), boshlanish sanasini o'zgartirib bo'lmaydi."]);
            }
            if ($k === 0 && $startsOn < today()->toDateString()) {
                throw ValidationException::withMessages(['starts_on' => "Boshlanish sanasi o'tmishda bo'lishi mumkin emas."]);
            }
            if ($count < $k) {
                throw ValidationException::withMessages(['lesson_count' => "Darslar soni kamida {$k} ta bo'lishi kerak: shuncha dars o'tgan yoki davomad olingan."]);
            }

            $remaining = $count - $k;
            $from = $k === 0
                ? $startsOn
                : max(today()->toDateString(), CarbonImmutable::parse($locked->last()->date)->addDay()->toDateString());
            $newDates = $remaining > 0 ? $this->schedule->lessonDates($from, $remaining, $schedule) : [];

            $this->assertNoConflicts($newDates, $roomId, $timeId, $teacherId, ignoreGroupId: $group->id);
        } elseif ($teacherChanged) {
            $futureDates = $group->days()->whereDate('date', '>=', today())->pluck('date')->map->toDateString()->all();
            $this->assertNoConflicts($futureDates, null, $group->lesson_time_id, $teacher->id, ignoreGroupId: $group->id);
        }

        // ---- Narx ----
        $plan = ! empty($data['price_plan_id']) ? PricePlan::active()->findOrFail((int) $data['price_plan_id']) : null;
        $priceDelta = $plan ? (int) $plan->amount - (int) $group->price : 0;
        $members = collect();
        if ($plan && $priceDelta !== 0) {
            $members = $group->activeMembers()->with('student')->get();

            if ($members->isNotEmpty() && empty($data['confirm_price_change'])) {
                $per = \App\Support\Format::money(abs($priceDelta));
                throw ValidationException::withMessages(['confirm_price_change' => "Guruhda {$members->count()} ta faol o'quvchi bor. Narx ".\App\Support\Format::money($group->price).' dan '
                    .\App\Support\Format::money($plan->amount)." ga o'zgarsa, har bir o'quvchi balansidan {$per} ".($priceDelta > 0 ? 'yechiladi' : 'qaytariladi').". Davom etish uchun tasdiqlash belgisini qo'ying."]);
            }
        }

        $notes = [];

        DB::transaction(function () use ($group, $data, $teacherId, $roomId, $timeId, $schedule, $scheduleChanged, $locked, $newDates, $plan, $priceDelta, $members, $actor, &$notes) {
            $group->update([
                'name' => mb_strtoupper(trim($data['name'])),
                'course_id' => Course::findOrFail($data['course_id'])->id,
                'teacher_id' => $teacherId,
                'teacher_rate' => (int) ($data['teacher_rate'] ?? 0),
                'teacher_bonus_rate' => (int) ($data['teacher_bonus_rate'] ?? 0),
            ]);

            if ($scheduleChanged) {
                GroupDay::where('group_id', $group->id)->whereNotIn('id', $locked->pluck('id')->all())->delete();

                if ($newDates !== []) {
                    GroupDay::insert(array_map(fn ($date) => [
                        'group_id' => $group->id, 'room_id' => $roomId, 'lesson_time_id' => $timeId, 'teacher_id' => $teacherId, 'date' => $date,
                    ], $newDates));
                }

                $all = GroupDay::where('group_id', $group->id)->orderBy('date')->get();

                $group->update([
                    'room_id' => $roomId,
                    'lesson_time_id' => $timeId,
                    'schedule' => $schedule,
                    'lesson_count' => $all->count(),
                    'starts_on' => $all->first()->date->toDateString(),
                    'ends_on' => $all->last()->date->toDateString(),
                ]);
                $notes[] = "jadval qayta tuzildi ({$all->count()} ta dars, ".count($newDates)." ta yangi sana)";
            } elseif ($group->wasChanged('teacher_id')) {
                GroupDay::where('group_id', $group->id)->whereDate('date', '>=', today())->update(['teacher_id' => $teacherId]);
            }

            if ($plan && ($priceDelta !== 0 || (int) $plan->early_discount !== (int) $group->early_discount || (int) $plan->max_discount !== (int) $group->max_discount)) {
                $old = (int) $group->price;
                $group->update(['price' => $plan->amount, 'early_discount' => $plan->early_discount, 'max_discount' => $plan->max_discount]);

                foreach ($members as $member) {
                    $this->balance->post(
                        $member->student, -$priceDelta, $priceDelta > 0 ? BalanceTransaction::CHARGE : BalanceTransaction::REFUND, $group,
                        "«{$group->name}» guruhi narxi o'zgardi: ".\App\Support\Format::money($old).' → '.\App\Support\Format::money($plan->amount), $actor,
                    );
                }

                $notes[] = 'narx '.\App\Support\Format::money($old).' → '.\App\Support\Format::money($plan->amount).($members->isNotEmpty() ? " ({$members->count()} ta o'quvchi balansi to'g'rilandi)" : '');
            }

            AuditLog::record('group.updated', $group, "Guruh yangilandi: {$group->name}".($notes ? ' — '.implode('; ', $notes) : ''));
        });

        return $group;
    }

    /**
     * Qulflangan darslar: o'tgan (bugundan oldingi) va davomad olingan darslar. Ular o'zgartirilmaydi.
     *
     * @return \Illuminate\Support\Collection<int, GroupDay>
     */
    public function lockedDays(Group $group)
    {
        $taken = AttendanceSession::where('group_id', $group->id)->pluck('date')->map(fn ($d) => CarbonImmutable::parse($d)->toDateString())->all();
        $today = today()->toDateString();

        return $group->days()->get()->filter(fn ($d) => $d->date->toDateString() < $today || in_array($d->date->toDateString(), $taken, true))->values();
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
