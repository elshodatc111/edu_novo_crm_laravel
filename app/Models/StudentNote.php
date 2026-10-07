<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * v8 B8: xodimlar o'rtasidagi ichki eslatmalar (o'quvchi profilidagi bitta "Eslatma" maydonidan farqli).
 * v13: eslatma «faolsizlantirilishi» mumkin (`closed_at`) - o'chmaydi, faqat qo'ng'iroqchadagi faol sondan chiqadi.
 */
class StudentNote extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'student_id', 'user_id', 'body'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNotNull('closed_at');
    }
}
