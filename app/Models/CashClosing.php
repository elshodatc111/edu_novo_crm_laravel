<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kassa smenasini yopish yozuvi: tizim kutgan naqt (jurnal bo'yicha) va xodim sanagan
 * haqiqiy naqt taqqoslanadi, farq FAQAT shu yerga yoziladi - kassa balansi o'zgarmaydi.
 */
class CashClosing extends Model
{
    use BelongsToBranch;

    public const UPDATED_AT = null;

    protected $fillable = ['branch_id', 'expected_cash', 'actual_cash', 'difference', 'note', 'closed_by'];

    protected function casts(): array
    {
        return ['expected_cash' => 'integer', 'actual_cash' => 'integer', 'difference' => 'integer', 'created_at' => 'datetime'];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
