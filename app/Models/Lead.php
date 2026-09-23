<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use BelongsToBranch;

    public const NEW = 'new';
    public const IN_PROGRESS = 'in_progress';
    public const CONVERTED = 'converted';
    public const CANCELLED = 'cancelled';

    protected $fillable = ['branch_id', 'name', 'phone', 'phone2', 'address', 'lead_source_id', 'status', 'is_repeat', 'student_id', 'created_by', 'ai_analysis', 'ai_analyzed_at'];

    protected function casts(): array
    {
        return ['is_repeat' => 'boolean', 'ai_analysis' => 'array', 'ai_analyzed_at' => 'datetime'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest('id');
    }

    public static function statusLabels(): array
    {
        return [self::NEW => 'Yangi', self::IN_PROGRESS => "Ko'rib chiqilmoqda", self::CONVERTED => 'Qabul qilindi', self::CANCELLED => 'Bekor qilindi'];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::NEW, self::IN_PROGRESS], true);
    }
}
