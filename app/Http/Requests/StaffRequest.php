<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Policies\UserPolicy;
use App\Rules\UniquePhonePerRole;
use App\Rules\UzPhone;
use App\Support\BranchContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $target = $this->route('user');
        $creating = $target === null;

        // v12 B: mobil API'da sAdmin filialni so'rov TANASI (branch_id) emas, `X-Branch-Id`
        // sarlavhasi (BranchContext) orqali ko'rsatadi - veb'da esa hamon forma dropdown'i ishlaydi.
        // Ikkala yo'l ham bitta joyga - BranchContext yoki so'rov tanasi - qaraydi, shu bilan
        // butun tizimda filial ko'rsatish mexanizmi izchil bo'ladi.
        $isApi = $this->is('api/*');

        $branchId = $target?->branch_id
            ?? ($this->user()->isSuperAdmin()
                ? ($isApi ? BranchContext::id() : (int) $this->input('branch_id'))
                : $this->user()->branch_id);

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[A-Za-z0-9._@+-]+$/', Rule::unique('users', 'username')->ignore($target?->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target?->id)],
            'phone' => ['required', 'string', new UzPhone, new UniquePhonePerRole(
                $branchId,
                $target?->role ?? (Role::tryFrom((string) $this->input('role')) ?? Role::Manager),
                $target?->id,
            )],
            'birthday' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'password' => [$creating ? 'required' : 'nullable', 'confirmed', Password::min(8)],
        ];

        if ($creating) {
            $allowedRoles = collect(UserPolicy::manageableRoles($this->user()))
                ->filter(fn (Role $r) => in_array($r, [Role::Admin, Role::Manager, Role::Teacher, Role::Operator], true))
                ->map->value->all();

            $rules['role'] = ['required', Rule::in($allowedRoles)];

            // API'da sAdmin uchun ham `branch_id` so'rov tanasida yubormaydi/kerak emas - `branch`
            // middleware allaqachon X-Branch-Id borligini tekshirgan bo'ladi (routes/api.php).
            $rules['branch_id'] = ($this->user()->isSuperAdmin() && ! $isApi)
                ? ['required', Rule::exists('branches', 'id')->where('status', 'active')]
                : ['prohibited'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'name' => 'F.I.O',
            'username' => 'Login',
            'email' => 'Email',
            'phone' => 'Telefon',
            'birthday' => "Tug'ilgan sana",
            'address' => 'Manzil',
            'status' => 'Holat',
            'password' => 'Parol',
            'role' => 'Lavozim',
            'branch_id' => 'Filial',
        ];
    }
}
