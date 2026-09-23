<?php

namespace App\Models;

use App\Enums\PayMethod;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToBranch;

    public const UPDATED_AT = null;

    public const PAYMENT = 'payment';
    public const DISCOUNT = 'discount';
    public const CAMPAIGN_BONUS = 'campaign_bonus';
    public const REFUND = 'refund';
    public const REVERSAL = 'reversal';

    /** Storno qilib bo'ladigan turlar (qaytarish - alohida "rad etish" oqimiga ega). */
    public const REVERSIBLE_TYPES = [self::PAYMENT, self::DISCOUNT, self::CAMPAIGN_BONUS];

    protected $fillable = [
        'branch_id', 'student_id', 'group_id', 'campaign_id', 'type', 'method', 'amount', 'description',
        'created_by', 'refund_confirmed_by', 'refund_confirmed_at',
        'reversal_of_id', 'reversed_by', 'reversed_at', 'reverse_reason',
        'refund_rejected_by', 'refund_rejected_at', 'refund_reject_reason',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime', 'amount' => 'integer', 'method' => PayMethod::class,
            'refund_confirmed_at' => 'datetime', 'reversed_at' => 'datetime', 'refund_rejected_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function isReversible(): bool
    {
        return in_array($this->type, self::REVERSIBLE_TYPES, true) && $this->reversed_at === null;
    }

    public function typeLabel(): string
    {
        return [
            self::PAYMENT => "To'lov",
            self::DISCOUNT => 'Chegirma',
            self::CAMPAIGN_BONUS => 'Aksiya bonusi',
            self::REFUND => 'Qaytarildi',
            self::REVERSAL => 'Storno',
        ][$this->type] ?? $this->type;
    }
}
