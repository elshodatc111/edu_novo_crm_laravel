<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Rules\UniquePhonePerRole;
use App\Rules\UzPhone;
use App\Support\BranchContext;
use Illuminate\Foundation\Http\FormRequest;

class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', new UzPhone, new UniquePhonePerRole($this->route('student')?->branch_id ?? BranchContext::id(), Role::Student, $this->route('student')?->id)],
            'phone2' => ['nullable', 'string', new UzPhone],
            'birthday' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:2000'],
            'lead_source_id' => ['nullable', BranchContext::exists('lead_sources')],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'F.I.O', 'phone' => 'Telefon', 'phone2' => "Qo'shimcha telefon", 'birthday' => "Tug'ilgan sana",
            'address' => 'Manzil', 'about' => 'Eslatma', 'lead_source_id' => 'Manba',
        ];
    }
}
