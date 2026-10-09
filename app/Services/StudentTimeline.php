<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BalanceTransaction;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * O'quvchi kartasidagi YAGONA tarix jadvali: balans harakatlari (to'lov usuli naqt/plastik bilan) va boshqa hodisalar
 * (guruhga qo'shildi/chiqdi, ma'lumot o'zgardi, arxiv va h.k.) vaqt bo'yicha bitta ro'yxatda.
 *
 * Pulga oid audit yozuvlari (to'lov, chegirma, qaytarish) balans yozuvlari bilan bir xil ma'lumot, shuning uchun
 * takrorlanmaydi: ularning o'rniga balans yozuvi ko'rsatiladi.
 */
class StudentTimeline
{
    /** Balans yozuvi bilan bir xil bo'lgani uchun ko'rsatilmaydigan audit harakatlari. */
    private const DUPLICATE_ACTIONS = ['payment.received', 'payment.discount', 'payment.refund'];

    /**
     * @return Collection<int, array{at:\Illuminate\Support\Carbon,kind:string,type:string,label:string,detail:?string,method:?string,amount:?int,balance:?int,by:?string,tone:string}>
     */
    public function for(User $student, int $limit = 150): Collection
    {
        $money = $student->balanceTransactions()->with(['group:id,name', 'creator:id,name', 'payment:id,method,type,reversed_at'])
            ->latest('id')->limit($limit)->get()
            ->map(function (BalanceTransaction $t) {
                $detail = collect([$t->group?->name, $t->note])->filter()->implode(' · ');
                $payment = $t->payment;

                return [
                    'at' => $t->created_at, 'sort' => 1, 'id' => $t->id,
                    'kind' => 'money', 'type' => $t->type, 'label' => $t->typeLabel(), 'detail' => $detail ?: null,
                    'method' => $payment?->method?->label(),
                    'amount' => (int) $t->amount, 'balance' => (int) $t->balance_after,
                    'by' => $t->creator?->name,
                    'payment_id' => $payment?->id,
                    'reversible' => (bool) $payment?->isReversible(),
                    'reversed' => (bool) $payment?->reversed_at,
                    'tone' => match ($t->type) {
                        BalanceTransaction::PAYMENT, BalanceTransaction::REFUND, BalanceTransaction::OPENING, BalanceTransaction::PAYMENT_REFUND_REJECTED => 'green',
                        BalanceTransaction::DISCOUNT, BalanceTransaction::MANUAL_DISCOUNT, BalanceTransaction::SPECIAL_DISCOUNT, BalanceTransaction::CAMPAIGN_BONUS => 'amber',
                        default => 'red',
                    },
                ];
            });

        $events = AuditLog::with('user:id,name')->where('subject_type', 'User')->where('subject_id', $student->id)
            ->whereNotIn('action', self::DUPLICATE_ACTIONS)
            ->where('action', 'not like', 'auth.%')
            ->latest('id')->limit($limit)->get()
            ->map(fn (AuditLog $a) => [
                'at' => $a->created_at, 'sort' => 0, 'id' => $a->id,
                'kind' => 'event', 'type' => $a->action, 'label' => $a->description ?? $a->action, 'detail' => null,
                'method' => null, 'amount' => null, 'balance' => null,
                'by' => $a->user?->name ?? 'Tizim', 'tone' => 'gray',
            ]);

        return $money->concat($events)
            ->sortByDesc(fn ($r) => [$r['at']->getTimestamp(), $r['sort'], $r['id']])
            ->take($limit)->values();
    }
}
