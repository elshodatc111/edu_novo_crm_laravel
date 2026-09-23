<?php

namespace App\Services;

use App\Enums\Wallet;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Support\Format;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Filialning barcha pul hamyonlari (kassa, moliya, ehson) bilan ishlash.
 * Har bir o'zgarish jurnalga yoziladi, qoldiq qulflab yangilanadi va manfiyga tushishi mumkin emas.
 */
class WalletService
{
    public function balance(int $branchId, Wallet $wallet): int
    {
        return (int) (WalletAccount::where('branch_id', $branchId)->where('code', $wallet->value)->value('balance') ?? 0);
    }

    /** @return array<string,int> */
    public function balances(int $branchId): array
    {
        $rows = WalletAccount::where('branch_id', $branchId)->pluck('balance', 'code');

        $result = [];
        foreach (Wallet::cases() as $wallet) {
            $result[$wallet->value] = (int) ($rows[$wallet->value] ?? 0);
        }

        return $result;
    }

    /**
     * @param  int  $amount  musbat - kirim, manfiy - chiqim
     *
     * @throws ValidationException mablag' yetarli bo'lmasa
     */
    public function post(int $branchId, Wallet $wallet, int $amount, string $type, ?string $description = null, ?Model $subject = null, ?User $actor = null, ?int $categoryId = null): WalletTransaction
    {
        return DB::transaction(function () use ($branchId, $wallet, $amount, $type, $description, $subject, $actor, $categoryId) {
            $account = $this->lockedAccount($branchId, $wallet);
            $newBalance = $account->balance + $amount;

            if ($newBalance < 0) {
                throw ValidationException::withMessages([
                    'amount' => "{$wallet->label()} da mablag' yetarli emas (mavjud: ".Format::money($account->balance).').',
                ]);
            }

            $account->update(['balance' => $newBalance]);

            return WalletTransaction::create([
                'branch_id' => $branchId,
                'wallet' => $wallet->value,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'type' => $type,
                'description' => $description,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'created_by' => $actor?->id,
                'category_id' => $categoryId,
            ]);
        });
    }

    private function lockedAccount(int $branchId, Wallet $wallet): WalletAccount
    {
        $find = fn () => WalletAccount::where('branch_id', $branchId)->where('code', $wallet->value)->lockForUpdate()->first();

        if ($account = $find()) {
            return $account;
        }

        try {
            WalletAccount::create(['branch_id' => $branchId, 'code' => $wallet->value, 'balance' => 0]);
        } catch (UniqueConstraintViolationException) {
            // Boshqa so'rov birinchi bo'lib yaratib qo'ygan
        }

        return $find();
    }
}
