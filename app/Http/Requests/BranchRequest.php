<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        $branchId = $this->route('branch')?->id;

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('branches', 'name')->ignore($branchId)],
            'phone' => ['nullable', 'string', new \App\Rules\UzPhone],
            'address' => ['nullable', 'string', 'max:255'],
            'eskiz_email' => ['nullable', 'email', 'max:255'],
            'eskiz_password' => ['nullable', 'string', 'max:255'],
            'eskiz_from' => ['nullable', 'string', 'max:30'],
            // v9: ochiq murojaat sahifasida Edunova o'rniga ko'rsatiladigan filial rangi va ma'lumoti.
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'public_about' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Filial nomi',
            'phone' => 'Telefon',
            'address' => 'Manzil',
            'eskiz_email' => 'Eskiz email',
            'eskiz_password' => 'Eskiz paroli',
            'eskiz_from' => 'Eskiz yuboruvchi nomi',
            'brand_color' => 'Rang',
            'public_about' => "Filial haqida ma'lumot",
        ];
    }
}
