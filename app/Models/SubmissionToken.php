<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ikki marta yuborishdan himoya uchun bir martalik tokenlar ("_once" maydoni yoki API Idempotency-Key).
 * Unique indeks orqali: bitta token faqat bitta so'rovda muvaffaqiyatli yozilishi mumkin.
 */
class SubmissionToken extends Model
{
    use MassPrunable;

    public $timestamps = false;

    protected $fillable = ['token', 'user_id'];

    protected $attributes = [];

    public static function booted(): void
    {
        static::creating(function (self $model) {
            $model->created_at ??= now();
        });
    }

    /** 24 soatdan eski tokenlar kerak emas (kunlik cron bilan tozalanadi). */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDay());
    }
}
