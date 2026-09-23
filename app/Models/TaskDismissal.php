<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kunlik vazifalar panelida "Bajardim" bosilgan kunlik yozuv. Filial global scope'i yo'q (sAdmin uchun ham ishlashi kerak). */
class TaskDismissal extends Model
{
    public $timestamps = false;

    protected $fillable = ['branch_id', 'user_id', 'task_key', 'dismissed_on'];

    // Diqqat: 'dismissed_on' ataylab 'date' sifatida cast qilinmagan — u har doim
    // faqat oddiy 'Y-m-d' satr sifatida (today()->toDateString()) solishtiriladi
    // (updateOrCreate va todo()dagi where() qidiruvida). 'date' cast qo'shilsa,
    // saqlashda vaqt qismi qo'shilib ('2026-09-21 00:00:00'), keyingi qidiruvlar
    // mos kelmay qoladi — dismiss() "ishlamay qoladi".

    protected static function booted(): void
    {
        static::creating(fn (self $d) => $d->created_at ??= now());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
