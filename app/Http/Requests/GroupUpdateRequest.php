<?php

namespace App\Http\Requests;

use App\Support\BranchContext;
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
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'Guruh nomi', 'course_id' => 'Kurs', 'teacher_id' => "O'qituvchi", 'teacher_rate' => "O'qituvchi stavkasi", 'teacher_bonus_rate' => 'Bonus stavkasi'];
    }
}
