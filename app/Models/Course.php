<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(CourseVideo::class)->orderBy('number');
    }

    public function audios(): HasMany
    {
        return $this->hasMany(CourseAudio::class)->orderBy('number');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(CourseQuestion::class);
    }
}
