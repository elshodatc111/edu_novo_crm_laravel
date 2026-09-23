<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupDay extends Model
{
    public $timestamps = false;

    protected $fillable = ['group_id', 'room_id', 'lesson_time_id', 'teacher_id', 'date'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function lessonTime(): BelongsTo
    {
        return $this->belongsTo(LessonTime::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
