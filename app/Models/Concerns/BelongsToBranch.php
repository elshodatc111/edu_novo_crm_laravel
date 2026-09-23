<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Filialga tegishli modellar uchun: avtomatik filial filtri va branch_id ni to'ldirish.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function ($model) {
            if (empty($model->branch_id)) {
                $branchId = BranchContext::id();

                if (! $branchId) {
                    throw new LogicException("Yozuv yaratish uchun filial tanlanishi kerak.");
                }

                $model->branch_id = $branchId;
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
