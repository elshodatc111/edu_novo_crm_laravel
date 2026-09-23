<?php

namespace App\Services;

use App\Enums\PayMethod;
use App\Enums\Wallet;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\Format;
use Illuminate\Validation\ValidationException;

/** Moliya balansi: egasi chiqimi, xarajatlar, ehson chiqimi va ehson foizi. */
class FinanceService
{
    /** Balansdan chiqim manbalari (ehsondan chiqim alohida: charityWithdraw). */
    public const WITHDRAW_SOURCES = ['cash', 'card'];

    public function __construct(private WalletService $wallets) {}

    /** Balansdan chiqim: 'cash' | 'card' */
    public function withdraw(string $source, int $amount, string $description, User $actor): void
    {
        $wallet = $this->withdrawWallet($source);

        $this->wallets->post($this->branchId(), $wallet, -$this->positive($amount), 'owner_withdrawal', $description, null, $actor);
        AuditLog::record('finance.withdrawal', null, "Moliyadan chiqim: ".Format::money($amount)." ({$wallet->label()}) — {$description}", branchId: $this->branchId());
    }

    /**
     * v9: egasi shaxsiy mablag'ini moliyaga kiritadi (naqt yoki plastik). Bu o'quvchi to'lovi emas,
     * shuning uchun ehson foizi ajratilmaydi va boshqa chiqimlardagi kabi tasdiqlash bosqichi yo'q — darhol yoziladi.
     */
    public function deposit(PayMethod $method, int $amount, string $description, User $actor): void
    {
        $wallet = $method->treasury();

        $this->wallets->post($this->branchId(), $wallet, $this->positive($amount), 'owner_deposit', $description, null, $actor);
        AuditLog::record('finance.deposit', null, "Shaxsiy mablag' kiritildi: ".Format::money($amount)." ({$wallet->label()}) — {$description}", branchId: $this->branchId());
    }

    /** Ehson balansidan chiqim: naqt ehsondan naqt, plastik ehsondan plastik sifatida. */
    public function charityWithdraw(PayMethod $method, int $amount, string $description, User $actor): void
    {
        $wallet = $method->charity();

        $this->wallets->post($this->branchId(), $wallet, -$this->positive($amount), 'charity_withdrawal', $description, null, $actor);
        AuditLog::record('finance.charity_withdrawal', null, "Ehson chiqimi: ".Format::money($amount)." ({$wallet->label()}) — {$description}", branchId: $this->branchId());
    }

    public function expense(PayMethod $method, int $amount, string $description, User $actor, ?int $categoryId = null): void
    {
        $this->wallets->post($this->branchId(), $method->treasury(), -$this->positive($amount), 'expense', $description, null, $actor, $categoryId);
        AuditLog::record('finance.expense', null, "Xarajat: ".Format::money($amount)." ({$method->label()}) — {$description}", branchId: $this->branchId());
    }

    public function setCharityPercent(float $percent): void
    {
        $branch = Branch::findOrFail($this->branchId());
        $branch->update(['charity_percent' => $percent]);

        AuditLog::record('finance.charity_percent', $branch, "Ehson foizi {$percent}% qilib belgilandi");
    }

    /** Chiqim/xarajat/ish haqi kiritilayotganda (tasdiqlashdan oldin) mablag' yetarliligini tekshiradi. */
    public function assertAvailable(Wallet $wallet, int $amount, string $field = 'amount'): int
    {
        $balance = $this->wallets->balance($this->branchId(), $wallet);

        if ($amount > $balance) {
            throw ValidationException::withMessages([$field => "{$wallet->label()} da mablag' yetarli emas (mavjud: ".Format::money($balance).').']);
        }

        return $balance;
    }

    public function withdrawWallet(string $source): Wallet
    {
        return match ($source) {
            'cash' => Wallet::TreasuryCash,
            'card' => Wallet::TreasuryCard,
            default => throw ValidationException::withMessages(['source' => "Balans turini tanlang."]),
        };
    }

    private function positive(int $amount): int
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => "Summani kiriting."]);
        }

        return $amount;
    }

    private function branchId(): int
    {
        return BranchContext::id() ?: throw ValidationException::withMessages(['amount' => 'Filial tanlanmagan.']);
    }
}
