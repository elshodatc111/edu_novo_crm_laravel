<?php

namespace App\Models;

use App\Enums\PayMethod;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    use BelongsToBranch;

    public const UPDATED_AT = null;

    protected $fillable = ['branch_id', 'recipient_id', 'group_id', 'method', 'amount', 'description', 'created_by'];

    protected function casts(): array
    {
        return ['method' => PayMethod::class, 'created_at' => 'datetime', 'amount' => 'integer'];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
