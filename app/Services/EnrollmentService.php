<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\BalanceTransaction;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Support\Format;
use Illuminate\Validation\ValidationException;

/** O'quvchini guruhga qo'shish va chiqarish (balans bilan birga). */
class EnrollmentService
{
    /** Tugagan guruhga shuncha kungacha qo'shish mumkin. */
    public const LATE_ENROLL_DAYS = 90;

    public function __construct(private BalanceService $balance, private EarlyDiscountService $early) {}

    /**
     * @param  bool  $allowDebt  qarzi bor (balansi manfiy) o'quvchini ISTISNO tariqasida qo'shish. Faqat
     *                           «groups.enroll_debtor» ruxsati bor foydalanuvchi (admin, sAdmin) uchun ishlaydi.
     */
    public function enroll(Group $group, User $student, ?string $note, User $actor, bool $allowDebt = false): GroupStudent
    {
        $this->assertStudent($student, $group);

        if ($group->ends_on->lt(today()->subDays(self::LATE_ENROLL_DAYS))) {
            throw ValidationException::withMessages(['group_id' => "Bu guruh juda oldin tugagan, o'quvchi qo'shib bo'lmaydi."]);
        }

        return DB::transaction(function () use ($group, $student, $note, $actor, $allowDebt) {
            // Bir vaqtda ikki marta qo'shilishiga qarshi: o'quvchi qatorini qulflaymiz
            $locked = User::whereKey($student->id)->lockForUpdate()->firstOrFail();

            if (GroupStudent::where('group_id', $group->id)->where('student_id', $student->id)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['group_id' => "O'quvchi bu guruhda allaqachon bor."]);
            }

            // Qarzi bor o'quvchi odatda guruhga qo'shilmaydi: avval to'lov qabul qilinadi.
            // Istisno: admin/sAdmin (ruxsat: groups.enroll_debtor) qarzli o'quvchini ham qo'sha oladi.
            $debtor = $locked->balance < 0;
            if ($debtor) {
                if (! $allowDebt || ! $actor->can('groups.enroll_debtor')) {
                    throw ValidationException::withMessages(['student_id' => "{$student->name} ning balansi manfiy (".Format::money($locked->balance)."). Qarzi bor o'quvchini guruhga qo'shib bo'lmaydi — avval to'lov qabul qiling."
                        .($actor->can('groups.enroll_debtor') ? " (Istisno tariqasida qo'shish uchun «Qarzga qo'shish» tasdig'ini belgilang.)" : '')]);
                }
            }

            $balanceBefore = $locked->balance;

            $this->balance->post($student, -$group->price, BalanceTransaction::CHARGE, $group, "«{$group->name}» guruhiga qo'shildi".($debtor ? ' (qarzdor, istisno)' : ''), $actor);

            $member = GroupStudent::create([
                'group_id' => $group->id,
                'student_id' => $student->id,
                'branch_id' => $group->branch_id,
                'added_by' => $actor->id,
                'add_note' => $note,
                'is_active' => true,
            ]);

            // Oldindan to'lov chegirmasi (balans yoki bir nechta to'lov bo'yicha) avtomatik beriladi
            $this->early->grantDue($student, $actor, $group, $balanceBefore);

            AuditLog::record('student.group_added', $student, "«{$group->name}» guruhiga qo'shildi (narxi ".number_format($group->price, 0, '', ' ')." so'm)"
                .($debtor ? ". Qarzdor o'quvchi istisno tariqasida qo'shildi (avvalgi balans: ".Format::money($balanceBefore).')' : ''));

            return $member;
        });
    }

    /**
     * O'quvchini guruhdan chiqaradi: narx qaytariladi, jarima (bo'lsa) yechiladi.
     */
    public function remove(Group $group, User $student, int $fine, ?string $note, User $actor): GroupStudent
    {
        if ($fine < 0 || $fine > $group->price) {
            throw ValidationException::withMessages(['fine' => "Jarima 0 dan guruh narxigacha bo'lishi kerak."]);
        }

        return DB::transaction(function () use ($group, $student, $fine, $note, $actor) {
            $member = GroupStudent::where('group_id', $group->id)
                ->where('student_id', $student->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $member) {
                throw ValidationException::withMessages(['student_id' => "O'quvchi bu guruhda faol emas."]);
            }

            $member->update([
                'is_active' => false,
                'removed_by' => $actor->id,
                'remove_note' => $note,
                'fine' => $fine,
                'left_at' => now(),
            ]);

            $this->balance->post($student, $group->price, BalanceTransaction::REFUND, $group, "«{$group->name}» guruhidan chiqarildi", $actor);

            if ($fine > 0) {
                $this->balance->post($student, -$fine, BalanceTransaction::FINE, $group, $note, $actor);
            }

            AuditLog::record('student.group_removed', $student, "«{$group->name}» guruhidan chiqarildi. Jarima: ".number_format($fine, 0, '', ' ')." so'm");

            return $member;
        });
    }

    private function assertStudent(User $student, Group $group): void
    {
        if ($student->role !== Role::Student || $student->archived_at !== null || $student->branch_id !== $group->branch_id) {
            throw ValidationException::withMessages(['student_id' => "Faqat faol o'quvchini guruhga qo'shish mumkin."]);
        }
    }
}
