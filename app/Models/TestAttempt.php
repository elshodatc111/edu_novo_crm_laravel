<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestAttempt extends Model
{
    use BelongsToBranch;

    public $timestamps = false;

    protected $fillable = ['branch_id', 'student_id', 'course_id', 'questions', 'answer_key', 'total', 'correct_count', 'score', 'started_at', 'finished_at'];

    protected $hidden = ['answer_key'];

    protected function casts(): array
    {
        return ['questions' => 'array', 'answer_key' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
