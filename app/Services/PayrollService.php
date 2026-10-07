<?php

namespace App\Services;

use App\Enums\PayMethod;
use App\Enums\Role;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Payout;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    /** Tugaganiga shuncha kun bo'lgan guruhlar ham ish haqi hisobida ko'rsatiladi. */
    public const RECENT_DAYS = 31;

    public function __construct(private WalletService $wallets) {}

    /**
     * O'qituvchining guruhlari bo'yicha hisoblangan ish haqi.
     *
     *  hisoblangan = faol o'quvchilar x stavka + bonusli o'quvchilar x bonus stavkasi
     *  bonusli o'quvchi = shu guruhdan keyin boshlangan boshqa guruhda ham faol o'quvchi
     *  davomad bo'yicha = o'tkazilgan darslar / jami darslar x hisoblangan
     *
     * @return array<int, array<string,mixed>>
     */
    public function teacherAccruals(User $teacher): array
    {
        $groups = Group::where('teacher_id', $teacher->id)
            ->whereDate('ends_on', '>=', today()->subDays(self::RECENT_DAYS))
            ->orderByDesc('starts_on')
            ->get();

        $ids = $groups->pluck('id');
        $held = AttendanceSession::whereIn('group_id', $ids)->selectRaw('group_id, count(*) as c')->groupBy('group_id')->pluck('c', 'group_id');
        $paid = Payout::whereIn('group_id', $ids)->where('recipient_id', $teacher->id)->selectRaw('group_id, sum(amount) as s')->groupBy('group_id')->pluck('s', 'group_id');

        return $groups->map(function (Group $group) use ($held, $paid) {
            $studentIds = GroupStudent::where('group_id', $group->id)->where('is_active', true)->pluck('student_id');
            $students = $studentIds->count();
            $bonus = $this->bonusCount($group, $studentIds->all());

            $accrued = $students * $group->teacher_rate + $bonus * $group->teacher_bonus_rate;
            $byAttendance = $group->lesson_count > 0 ? (int) round(($held[$group->id] ?? 0) * $accrued / $group->lesson_count) : 0;
            $paidSum = (int) ($paid[$group->id] ?? 0);

            return [
                'group' => $group,
                'students' => $students,
                'bonus' => $bonus,
                'held' => (int) ($held[$group->id] ?? 0),
                'accrued' => $accrued,
                'accrued_by_attendance' => $byAttendance,
                'paid' => $paidSum,
                'remaining' => $byAttendance - $paidSum,
            ];
        })->all();
    }

    public function payTeacher(User $teacher, ?Group $group, PayMethod $method, int $amount, ?string $description, User $actor): Payout
    {
        if ($teacher->role !== Role::Teacher) {
            throw ValidationException::withMessages(['recipient' => "Bu foydalanuvchi o'qituvchi emas."]);
        }

        return $this->pay($teacher, $group, $method, $amount, $description, 'salary_teacher', $actor);
    }

    public function payStaff(User $staff, PayMethod $method, int $amount, ?string $description, User $actor): Payout
    {
        if (! in_array($staff->role, [Role::Admin, Role::Manager, Role::Operator], true)) {
            throw ValidationException::withMessages(['recipient' => "Bu foydalanuvchi hodim emas."]);
        }

        return $this->pay($staff, null, $method, $amount, $description, 'salary_staff', $actor);
    }

    private function pay(User $recipient, ?Group $group, PayMethod $method, int $amount, ?string $description, string $type, User $actor): Payout
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Summani kiriting.']);
        }
        if ($group && $group->teacher_id !== $recipient->id) {
            throw ValidationException::withMessages(['group_id' => "Bu guruh shu o'qituvchiga tegishli emas."]);
        }

        $payout = DB::transaction(function () use ($recipient, $group, $method, $amount, $description, $type, $actor) {
            $payout = Payout::create([
                'branch_id' => $recipient->branch_id, 'recipient_id' => $recipient->id, 'group_id' => $group?->id,
                'method' => $method, 'amount' => $amount, 'description' => $description, 'created_by' => $actor->id,
            ]);

            $this->wallets->post($recipient->branch_id, $method->treasury(), -$amount, $type, trim("{$recipient->name}. {$description}"), $payout, $actor);
            AuditLog::record('payroll.paid', $recipient, "Ish haqi to'landi: ".Format::money($amount)." ({$method->label()})");

            return $payout;
        });

        app(SmsNotifier::class)->notify('staff_paid', $recipient, ['amount' => Format::money($amount)]);

        return $payout;
    }

    /** Guruh o'quvchilaridan nechtasi keyinroq boshlangan boshqa guruhda ham faol. */
    private function bonusCount(Group $group, array $studentIds): int
    {
        if ($studentIds === []) {
            return 0;
        }

        return GroupStudent::whereIn('student_id', $studentIds)
            ->where('is_active', true)
            ->where('group_id', '!=', $group->id)
            ->whereIn('group_id', Group::whereDate('starts_on', '>=', $group->starts_on)->select('id'))
            ->distinct()
            ->count('student_id');
    }
}
