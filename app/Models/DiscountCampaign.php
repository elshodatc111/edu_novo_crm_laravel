<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Aksiya: ma'lum summada to'lov qilsa, qo'shimcha bonus beriladi. */
class DiscountCampaign extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'name', 'amount', 'bonus', 'starts_on', 'ends_on', 'is_active'];

    protected function casts(): array
    {
        return ['starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d', 'is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Bugun amal qiladigan aksiyalar. */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->active()->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today());
    }
}
