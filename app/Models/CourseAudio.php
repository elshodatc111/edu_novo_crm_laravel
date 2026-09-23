<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class CourseAudio extends Model
{
    use BelongsToBranch;

    // "audio" so'zi ko'plikka o'zgarmagani uchun jadval nomi aniq ko'rsatiladi
    protected $table = 'course_audios';

    protected $fillable = ['branch_id', 'course_id', 'number', 'title', 'path', 'is_external', 'created_by'];

    protected function casts(): array
    {
        return ['is_external' => 'boolean'];
    }

    /** O'quvchiga beriladigan to'liq havola. */
    public function getUrlAttribute(): string
    {
        return $this->is_external ? $this->path : asset('storage/'.$this->path);
    }
}
