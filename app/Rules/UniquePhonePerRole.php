<?php

namespace App\Rules;

use App\Enums\Role;
use App\Models\User;
use App\Support\Format;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Bir filialda bir rolda bitta telefon raqami faqat bir marta bo'lishi mumkin. */
class UniquePhonePerRole implements ValidationRule
{
    public function __construct(private ?int $branchId, private Role $role, private ?int $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $phone = Format::canonicalPhone((string) $value);

        if (! $phone || ! $this->branchId) {
            return;
        }

        $exists = User::where('branch_id', $this->branchId)->where('role', $this->role)->where('phone', $phone)
            ->when($this->ignoreId, fn ($q) => $q->where('id', '!=', $this->ignoreId))->exists();

        if ($exists) {
            $fail("Bu telefon raqami bilan {$this->role->label()} bu filialda allaqachon ro'yxatdan o'tgan.");
        }
    }
}
