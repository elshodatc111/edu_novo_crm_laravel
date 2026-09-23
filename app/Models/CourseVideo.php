<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class CourseVideo extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'course_id', 'number', 'title', 'url', 'created_by'];

    protected function casts(): array
    {
        return [];
    }
}
