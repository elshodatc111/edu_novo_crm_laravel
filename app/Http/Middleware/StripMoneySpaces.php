<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** "1 250 000" ko'rinishida kiritilgan summalardan bo'shliqlarni olib tashlaydi. */
class StripMoneySpaces
{
    private const FIELDS = [
        'cash', 'card', 'amount', 'special_amount', 'bonus', 'early_discount', 'max_discount', 'fine',
        'teacher_rate', 'teacher_bonus_rate', 'balance',
        'usluga_student_1', 'usluga_student_2', 'usluga_student_3', 'usluga_student_4',
        'usluga_bonus_1', 'usluga_bonus_2', 'usluga_bonus_3', 'usluga_bonus_4',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $clean = [];
        foreach (self::FIELDS as $field) {
            $value = $request->input($field);
            if (is_string($value) && preg_match('/\s/u', $value)) {
                $clean[$field] = preg_replace('/[\s\x{00A0}]+/u', '', $value);
            }
        }

        if ($clean) {
            $request->merge($clean);
        }

        return $next($request);
    }
}
