<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Telefon raqami faqat +998 90 123 4567 ko'rinishida qabul qilinadi. */
class UzPhone implements ValidationRule
{
    public const PATTERN = '/^\+998 \d{2} \d{3} \d{4}$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, $value)) {
            $fail(':attribute +998 90 123 4567 ko\'rinishida bo\'lishi kerak.');
        }
    }
}
