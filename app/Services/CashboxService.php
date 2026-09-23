<?php

namespace App\Services;

use App\Enums\PayMethod;
use App\Enums\Wallet;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CashClosing;
use App\Models\CashRequest;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\Format;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kassadan chiqim va xarajat so'rovlari.
 * So'rov yaratilganda pul kassadan darhol yechiladi; admin tasdiqlaydi yoki bekor qiladi (pul kassaga qaytadi).
 */
class CashboxService
{
    public function __construct(private WalletService $wallets) {}

    /** v8 B3: $categoryId faqat "xarajat" turida ma'noli, boshqa holatda e'tiborsiz qoldiriladi. */
    public function request(string $kind, PayMethod $method, int $amount, string $description, User $actor, ?int $categoryId = null): CashRequest
    {
        if (! in_array($kind, [CashRequest::WITHDRAWAL, CashRequest::EXPENSE], true) || $amount <= 0) {
            throw ValidationException::withMessages(['amount' => "Summani to'g'ri kiriting."]);
        }

        $branchId = BranchContext::id();
        $categoryId = $kind === CashRequest::EXPENSE ? $categoryId : null;
        if ($categoryId !== null && ! ExpenseCategory::where('branch_id', $branchId)->where('id', $categoryId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['category_id' => "Xarajat turi topilmadi."]);
        }

        return DB::transaction(function () use ($kind, $categoryId, $method, $amount, $description, $actor, $branchId) {
            $request = CashRequest::create([
                'branch_id' => $branchId, 'kind' => $kind, 'category_id' => $categoryId, 'method' => $method, 'amount' => $amount,
                'description' => $description, 'status' => CashRequest::PENDING, 'requested_by' => $actor->id,
            ]);

            $this->wallets->post($branchId, $method->till(), -$amount, 'cash_request', $description, $request, $actor);
            AuditLog::record('cashbox.requested', $request, "{$request->kindLabel()} so'rovi: ".Format::money($amount)." ({$method->label()})");

            return $request;
        });
    }

    /**
     * v8 B3: kassa smenasini yopish. Tizim kutgan naqt (jurnal bo'yicha) hisoblanadi,
     * xodim sanagan haqiqiy naqt bilan solishtiriladi. Farq FAQAT yoziladi - kassa balansi
     * (WalletAccount) bu yerda hech qachon o'zgartirilmaydi (qo'lda tuzatish taqiqlangan).
     */
    public function close(int $actualCash, ?string $note, User $actor): CashClosing
    {
        if ($actualCash < 0) {
            throw ValidationException::withMessages(['actual_cash' => "Summani to'g'ri kiriting."]);
        }

        $branchId = BranchContext::id();
        $expected = $this->wallets->balance($branchId, Wallet::TillCash);

        $closing = CashClosing::create([
            'branch_id' => $branchId, 'expected_cash' => $expected, 'actual_cash' => $actualCash,
            'difference' => $actualCash - $expected, 'note' => $note, 'closed_by' => $actor->id,
        ]);

        $diffText = $closing->difference === 0 ? 'farqsiz' : ($closing->difference > 0 ? '+'.Format::money($closing->difference).' ortiqcha' : Format::money(abs($closing->difference)).' kam');
        AuditLog::record('cashbox.closed', $closing, "Kassa smenasi yopildi: kutilgan ".Format::money($expected).", haqiqiy ".Format::money($actualCash)." ({$diffText})");

        return $closing;
    }

    /**
     * Tasdiqlash. Chiqim bo'lsa pul moliya balansiga o'tadi (ehson foizi alohida ajratiladi),
     * xarajat bo'lsa pul sarflangan hisoblanadi.
     */
    public function approve(CashRequest $request, User $actor): CashRequest
    {
        return DB::transaction(function () use ($request, $actor) {
            $locked = $this->lockPending($request);

            if ($locked->kind === CashRequest::WITHDRAWAL) {
                $percent = (float) Branch::findOrFail($locked->branch_id)->charity_percent;
                $charity = (int) round($locked->amount * $percent / 100);

                $this->wallets->post($locked->branch_id, $locked->method->treasury(), $locked->amount - $charity, 'withdrawal_in', $locked->description, $locked, $actor);
                if ($charity > 0) {
                    $this->wallets->post($locked->branch_id, $locked->method->charity(), $charity, 'charity_in', "Ehson ({$percent}%)", $locked, $actor);
                }
            }

            $locked->update(['status' => CashRequest::APPROVED, 'decided_by' => $actor->id, 'decided_at' => now()]);
            AuditLog::record('cashbox.approved', $locked, "{$locked->kindLabel()} tasdiqlandi: ".Format::money($locked->amount));

            return $locked;
        });
    }

    /** Bekor qilish: pul kassaga qaytadi. */
    public function cancel(CashRequest $request, User $actor): CashRequest
    {
        return DB::transaction(function () use ($request, $actor) {
            $locked = $this->lockPending($request);

            $this->wallets->post($locked->branch_id, $locked->method->till(), $locked->amount, 'cash_request_cancel', $locked->description, $locked, $actor);
            $locked->update(['status' => CashRequest::CANCELLED, 'decided_by' => $actor->id, 'decided_at' => now()]);
            AuditLog::record('cashbox.cancelled', $locked, "{$locked->kindLabel()} bekor qilindi: ".Format::money($locked->amount));

            return $locked;
        });
    }

    private function lockPending(CashRequest $request): CashRequest
    {
        $locked = CashRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== CashRequest::PENDING) {
            throw ValidationException::withMessages(['request' => "Bu so'rov allaqachon ko'rib chiqilgan."]);
        }

        return $locked;
    }
}
