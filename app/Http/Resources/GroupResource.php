<?php

namespace App\Http\Resources;

use App\Enums\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Group */
class GroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hidePrice = $request->user()?->role === Role::Teacher;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'course' => ['id' => $this->course_id, 'name' => $this->course?->name],
            'teacher' => ['id' => $this->teacher_id, 'name' => $this->teacher?->name],
            'room' => $this->room?->name,
            'lesson_time' => $this->lessonTime?->label,
            'schedule' => $this->schedule->value,
            'schedule_label' => $this->schedule->label(),
            'price' => $hidePrice ? null : $this->price,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'lesson_count' => $this->lesson_count,
            'status' => $this->status,
            'students_count' => $this->students_count,
        ];
    }
}
