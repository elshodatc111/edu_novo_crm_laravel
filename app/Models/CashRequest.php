<?php

namespace App\Models;

use App\Enums\PayMethod;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashRequest extends Model
{
    use BelongsToBranch;

    public const WITHDRAWAL = 'withdrawal';
    public const EXPENSE = 'expense';
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const CANCELLED = 'cancelled';

    protected $fillable = ['branch_id', 'kind', 'category_id', 'method', 'amount', 'description', 'status', 'requested_by', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['method' => PayMethod::class, 'decided_at' => 'datetime', 'amount' => 'integer'];
    }

    /** v8 B3: faqat "xarajat" turidagi so'rovlar uchun ma'noli, ixtiyoriy. */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** v13: so'rov turi uchun kerakli ruxsat: chiqim - `cashbox.withdraw`, xarajat - `cashbox.request`. */
    public static function permissionFor(string $kind): string
    {
        return $kind === self::WITHDRAWAL ? 'cashbox.withdraw' : 'cashbox.request';
    }

    public function permission(): string
    {
        return self::permissionFor($this->kind);
    }

    public function kindLabel(): string
    {
        return $this->kind === self::WITHDRAWAL ? 'Chiqim (moliyaga)' : 'Xarajat';
    }
}
