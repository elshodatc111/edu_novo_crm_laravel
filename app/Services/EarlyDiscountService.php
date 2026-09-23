<?php

namespace App\Services;

use App\Models\BalanceTransaction;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Oldindan to'lov chegirmasi (avtomatik). Har bir to'lovdan va guruhga qo'shilgandan keyin chaqiriladi,
 * shuning uchun bir nechta kichik to'lov ham hisobga olinadi.
 *
 * O'quvchi FAOL bo'lgan, chegirma muddati (DiscountWindow) ichidagi va hali chegirma olmagan har bir guruh uchun
 * chegirma beriladi, agar:
 *  (a) guruhga qo'shilayotganda balansi narx − chegirmani qoplasa (oldindan to'lagan), YOKI
 *  (b) chegirma muddati boshlanganidan beri qilingan JAMI to'lovlar (bir necha marta bo'lsa ham; qaytarilganlari
 *      ayirilgan, guruh tanlangan yoki tanlanmaganidan qat'i nazar) narx − chegirmaga yetsa
 *      va o'quvchining umumiy qarzi shu guruh narxidan oshmasa.
 * Bir nechta guruh bo'lsa, bitta to'lov pulini ikki guruhga ikki marta hisoblamaslik uchun
 * guruhlar boshlanish sanasi bo'yicha tartiblanib, talab qilingan summalar yig'ilib boriladi.
 * Bir o'quvchiga bir guruh uchun chegirma faqat bir marta beriladi.
 */
class EarlyDiscountService
{
    public function __construct(private BalanceService $balance, private DiscountWindow $window) {}

    /**
     * @return array<int, Payment> berilgan chegirmalar
     */
    public function grantDue(User $student, User $actor, ?Group $justEnrolled = null, ?int $balanceBeforeEnroll = null): array
    {
        $groups = GroupStudent::where('student_id', $student->id)->where('is_active', true)
            ->with('group')->get()->pluck('group')->filter()
            ->filter(fn (Group $g) => $g->early_discount > 0 && $this->window->allows($g))
            ->sortBy([['starts_on', 'asc'], ['id', 'asc']]);

        $granted = [];
        $cumulative = 0;

        foreach ($groups as $group) {
            $need = max(0, $group->price - $group->early_discount);
            $cumulative += $need;

            if ($this->hasDiscount($student, $group)) {
                continue;
            }

            $balance = (int) User::whereKey($student->id)->value('balance');

            $prepaid = $justEnrolled && $group->is($justEnrolled) && $balanceBeforeEnroll !== null && $balanceBeforeEnroll >= $need;
            $installments = ! $prepaid
                && $this->paidSinceWindowOpened($student, $group) >= $cumulative
                && max(0, -$balance) <= $group->price;

            if ($prepaid || $installments) {
                $granted[] = $this->grant($student, $group, $group->early_discount, "Oldindan to'lov uchun chegirma", BalanceTransaction::DISCOUNT, $actor);
            }
        }

        return $granted;
    }

    /** Chegirmani to'lovlar jadvaliga va o'quvchi balansiga yozadi. */
    public function grant(User $student, Group $group, int $amount, string $note, string $balanceType, User $actor): Payment
    {
        $payment = Payment::create([
            'branch_id' => $student->branch_id, 'student_id' => $student->id, 'group_id' => $group->id, 'type' => Payment::DISCOUNT,
            'amount' => $amount, 'description' => $note, 'created_by' => $actor->id,
        ]);

        $this->balance->post($student, $amount, $balanceType, $group, $note, $actor, $payment);

        return $payment;
    }

    public function hasDiscount(User $student, Group $group): bool
    {
        return Payment::where('student_id', $student->id)->where('group_id', $group->id)->where('type', Payment::DISCOUNT)->whereNull('reversed_at')->exists();
    }

    /**
     * Chegirma muddati boshlanganidan beri sof to'langan summa (to'lovlar − qaytarishlar).
     * Storno qilingan to'lovlar "to'langan" summaga kirmaydi; rad etilgan (pul qaytarilgan) qaytarishlar
     * esa "qaytarilgan" summaga kirmaydi — ikkalasi ham "go'yo bo'lmagandek" hisoblanadi.
     */
    public function paidSinceWindowOpened(User $student, Group $group): int
    {
        [$before] = $this->window->days($group->branch_id);
        $from = CarbonImmutable::parse($group->starts_on)->subDays($before)->startOfDay();

        $sums = Payment::where('student_id', $student->id)->where('created_at', '>=', $from)
            ->whereIn('type', [Payment::PAYMENT, Payment::REFUND])
            ->selectRaw("
                coalesce(sum(case when type = 'payment' and reversed_at is null then amount end), 0) as paid,
                coalesce(sum(case when type = 'refund' and refund_rejected_at is null then amount end), 0) as refunded
            ")
            ->first();

        return (int) $sums->paid - (int) $sums->refunded;
    }
}
