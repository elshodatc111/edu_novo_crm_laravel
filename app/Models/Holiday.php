<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'date', 'comment'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }
}
