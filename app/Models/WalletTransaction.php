<?php

namespace App\Models;

use App\Enums\Wallet;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use BelongsToBranch;

    public const UPDATED_AT = null;

    protected $fillable = ['branch_id', 'wallet', 'amount', 'balance_after', 'type', 'description', 'subject_type', 'subject_id', 'created_by', 'category_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'amount' => 'integer', 'balance_after' => 'integer'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** v10: to'g'ridan-to'g'ri moliya xarajati uchun ixtiyoriy xarajat turi. */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function walletLabel(): string
    {
        return Wallet::tryFrom($this->wallet)?->label() ?? $this->wallet;
    }

    public function typeLabel(): string
    {
        return [
            'payment_in' => "Talaba to'lovi",
            'refund_out' => "To'lov qaytarildi",
            'cash_request' => "Kassadan so'rov (chiqim/xarajat)",
            'cash_request_cancel' => "So'rov bekor qilindi",
            'withdrawal_in' => 'Kassadan moliyaga o\'tkazildi',
            'charity_in' => 'Ehson ulushi',
            'charity_split' => "Ehson naqt/plastikka bo'lindi",
            'charity_withdrawal' => 'Ehson chiqimi',
            'owner_withdrawal' => 'Moliyadan chiqim',
            'owner_deposit' => "Shaxsiy mablag' kiritildi",
            'expense' => 'Xarajat',
            'salary_teacher' => "O'qituvchi ish haqi",
            'salary_staff' => 'Hodim ish haqi',
        ][$this->type] ?? $this->type;
    }
}
