<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'attendance_session_id', 'group_id', 'student_id', 'date', 'is_present'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'is_present' => 'boolean'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
