<?php

namespace App\Http\Requests;

use App\Enums\Schedule;
use App\Support\BranchContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'course_id' => ['required', BranchContext::exists('courses')],
            'teacher_id' => ['required', BranchContext::exists('users')->where('role', 'teacher')],
            'room_id' => ['required', BranchContext::exists('rooms')],
            'lesson_time_id' => ['required', BranchContext::exists('lesson_times')],
            'price_plan_id' => ['required', BranchContext::exists('price_plans')],
            'schedule' => ['required', Rule::enum(Schedule::class)],
            'starts_on' => ['required', 'date'],
            'lesson_count' => ['required', 'integer', 'min:1', 'max:60'],
            'teacher_rate' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'teacher_bonus_rate' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'students' => ['nullable', 'array'],
            'students.*' => ['integer'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Guruh nomi', 'course_id' => 'Kurs', 'teacher_id' => "O'qituvchi", 'room_id' => 'Xona',
            'lesson_time_id' => 'Dars vaqti', 'price_plan_id' => 'Narx rejasi', 'schedule' => 'Dars kunlari',
            'starts_on' => 'Boshlanish sanasi', 'lesson_count' => 'Darslar soni',
            'teacher_rate' => "O'qituvchi stavkasi", 'teacher_bonus_rate' => 'Bonus stavkasi',
        ];
    }
}
