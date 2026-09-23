<?php

namespace App\Support;

class Format
{
    /** 1250000 -> "1 250 000 so'm" */
    public static function money(int|float|null $amount, bool $withUnit = true): string
    {
        $text = number_format((int) $amount, 0, '', ' ');

        return $withUnit ? $text." so'm" : $text;
    }

    /** Telefon raqamidan faqat raqamlarni qoldiradi. */
    public static function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value);
    }

    /**
     * Telefon raqamini xalqaro ko'rinishga keltiradi: 998901234567.
     * To'g'ri emas bo'lsa null qaytaradi.
     */
    public static function normalizePhone(?string $value): ?string
    {
        $digits = self::digits($value);

        if (strlen($digits) === 9) {
            return '998'.$digits;
        }

        return strlen($digits) === 12 && str_starts_with($digits, '998') ? $digits : null;
    }

    /** Telefon raqamining yagona ko'rinishi: +998 90 123 4567 (noto'g'ri bo'lsa null). */
    public static function canonicalPhone(?string $value): ?string
    {
        $n = self::normalizePhone($value);

        return $n ? sprintf('+%s %s %s %s', substr($n, 0, 3), substr($n, 3, 2), substr($n, 5, 3), substr($n, 8, 4)) : null;
    }

    /** Ko'rsatish uchun: to'g'ri raqam +998 90 123 4567 ko'rinishida, aks holda o'zi. */
    public static function prettyPhone(?string $value): string
    {
        return self::canonicalPhone($value) ?? (string) $value;
    }
}
