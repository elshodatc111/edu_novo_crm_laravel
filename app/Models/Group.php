<?php

namespace App\Models;

use App\Enums\Schedule;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use BelongsToBranch;
    use SoftDeletes; // v13: boshlanmagan guruh arxivlanadi (o'chirilmaydi)

    public const NEW = 'new';
    public const ACTIVE = 'active';
    public const FINISHED = 'finished';

    protected $fillable = [
        'branch_id', 'course_id', 'room_id', 'lesson_time_id', 'teacher_id', 'next_group_id', 'created_by',
        'name', 'schedule', 'price', 'early_discount', 'max_discount', 'lesson_count', 'starts_on', 'ends_on',
        'teacher_rate', 'teacher_bonus_rate',
    ];

    protected function casts(): array
    {
        return [
            'schedule' => Schedule::class,
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function lessonTime(): BelongsTo
    {
        return $this->belongsTo(LessonTime::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function nextGroup(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_group_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(GroupDay::class)->orderBy('date');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupStudent::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->hasMany(GroupStudent::class)->where('is_active', true);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    /** new | active | finished (bugungi sanaga nisbatan) */
    public function getStatusAttribute(): string
    {
        $today = today();

        return match (true) {
            $today->lt($this->starts_on) => self::NEW,
            $today->gt($this->ends_on) => self::FINISHED,
            default => self::ACTIVE,
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::NEW => 'Boshlanmagan',
            self::ACTIVE => 'Davom etmoqda',
            default => 'Tugagan',
        };
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        $today = today()->toDateString();

        return match ($status) {
            self::NEW => $query->where('starts_on', '>', $today),
            self::ACTIVE => $query->where('starts_on', '<=', $today)->where('ends_on', '>=', $today),
            self::FINISHED => $query->where('ends_on', '<', $today),
            'current' => $query->where('ends_on', '>=', $today),
            default => $query,
        };
    }

    /** Bugun dars bo'ladigan guruhlar. */
    public function scopeHavingLessonOn(Builder $query, $date): Builder
    {
        return $query->whereHas('days', fn ($q) => $q->whereDate('date', $date));
    }
}
