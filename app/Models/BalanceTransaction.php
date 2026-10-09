<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceTransaction extends Model
{
    use BelongsToBranch;

    public const UPDATED_AT = null;

    public const CHARGE = 'charge';       // guruhga qo'shilganda narx yechiladi
    public const DISCOUNT = 'discount';   // oldindan to'lov chegirmasi
    public const REFUND = 'refund';       // guruhdan chiqarilganda narx qaytadi
    public const FINE = 'fine';           // jarima
    public const PAYMENT = 'payment';     // to'lov qabul qilindi
    public const MANUAL_DISCOUNT = 'manual_discount';
    public const SPECIAL_DISCOUNT = 'special_discount'; // v13.2: sAdmin maxsus chegirmasi (guruhga bog'lanmagan)
    public const CAMPAIGN_BONUS = 'campaign_bonus';
    public const PAYMENT_REFUND = 'payment_refund';
    public const OPENING = 'opening';     // import: boshlang'ich balans
    public const PAYMENT_REVERSAL = 'payment_reversal';       // v8: xato to'lov/chegirma/bonus stornosi
    public const PAYMENT_REFUND_REJECTED = 'refund_rejected'; // v8: qaytarish rad etildi, pul qaytdi

    protected $fillable = ['branch_id', 'student_id', 'type', 'amount', 'balance_after', 'group_id', 'payment_id', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class)->withTrashed();
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::CHARGE => "Guruh narxi",
            self::DISCOUNT => "Oldindan to'lov chegirmasi",
            self::REFUND => 'Guruhdan chiqarildi (qaytarildi)',
            self::FINE => 'Jarima',
            self::PAYMENT => "To'lov",
            self::MANUAL_DISCOUNT => 'Chegirma (admin)',
            self::SPECIAL_DISCOUNT => 'Maxsus chegirma (sAdmin)',
            self::CAMPAIGN_BONUS => 'Aksiya bonusi',
            self::PAYMENT_REFUND => "To'lov qaytarildi",
            self::OPENING => "Boshlang'ich balans (import)",
            self::PAYMENT_REVERSAL => 'Storno (xato yozuv bekor qilindi)',
            self::PAYMENT_REFUND_REJECTED => 'Qaytarish rad etildi',
            default => $this->type,
        };
    }
}
