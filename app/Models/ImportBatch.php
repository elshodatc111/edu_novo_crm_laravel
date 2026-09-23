<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $fillable = ['user_id', 'status', 'filename', 'summary', 'rows', 'result_path'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'rows' => 'array'];
    }
}
