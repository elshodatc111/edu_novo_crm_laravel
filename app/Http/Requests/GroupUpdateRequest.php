<?php

namespace App\Http\Requests;

use App\Enums\Schedule;
use App\Support\BranchContext;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class GroupUpdateRequest extends FormRequest
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
            'teacher_rate' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'teacher_bonus_rate' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            // v13: jadval va narx ham tahrirlanadi (hammasi ixtiyoriy: yuborilmasa, hozirgi qiymat saqlanadi)
            'room_id' => ['nullable', BranchContext::exists('rooms')],
            'lesson_time_id' => ['nullable', BranchContext::exists('lesson_times')],
            'schedule' => ['nullable', Rule::enum(Schedule::class)],
            'starts_on' => ['nullable', 'date'],
            'lesson_count' => ['nullable', 'integer', 'min:1', 'max:60'],
            'price_plan_id' => ['nullable', BranchContext::exists('price_plans')],
            'confirm_price_change' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'Guruh nomi', 'course_id' => 'Kurs', 'teacher_id' => "O'qituvchi", 'teacher_rate' => "O'qituvchi stavkasi", 'teacher_bonus_rate' => 'Bonus stavkasi',
            'room_id' => 'Xona', 'lesson_time_id' => 'Dars vaqti', 'schedule' => 'Dars kunlari', 'starts_on' => 'Boshlanish sanasi', 'lesson_count' => 'Darslar soni', 'price_plan_id' => 'Narx rejasi'];
    }
}
