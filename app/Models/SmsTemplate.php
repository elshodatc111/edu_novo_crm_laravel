<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'key', 'body', 'is_enabled'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }
}
