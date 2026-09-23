<?php

namespace App\Services;

use App\Models\BalanceTransaction;
use App\Models\Group;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * O'quvchi balansi bilan barcha operatsiyalar shu yerdan o'tadi:
 * har bir o'zgarish jurnalga (balance_transactions) yoziladi va balans qulflangan holda yangilanadi.
 */
class BalanceService
{
    /**
     * @param  int  $amount  musbat - balansga qo'shiladi, manfiy - yechiladi
     */
    public function post(User $student, int $amount, string $type, ?Group $group = null, ?string $note = null, ?User $actor = null, ?Payment $payment = null): BalanceTransaction
    {
        return DB::transaction(function () use ($student, $amount, $type, $group, $note, $actor, $payment) {
            $locked = User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $locked->balance += $amount;
            $locked->save();

            $student->balance = $locked->balance;

            return BalanceTransaction::create([
                'branch_id' => $locked->branch_id,
                'student_id' => $locked->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $locked->balance,
                'group_id' => $group?->id,
                'payment_id' => $payment?->id,
                'note' => $note,
                'created_by' => $actor?->id,
            ]);
        });
    }
}
