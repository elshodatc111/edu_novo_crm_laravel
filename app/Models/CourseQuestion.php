<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class CourseQuestion extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'course_id', 'question', 'correct', 'wrong', 'created_by'];

    protected function casts(): array
    {
        return ['wrong' => 'array'];
    }
}
