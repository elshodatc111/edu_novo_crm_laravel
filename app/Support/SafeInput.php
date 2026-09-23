<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * v8 A6: ro'yxat/filtr sahifalarida so'rov parametrlarini xavfsiz o'qiydi - hech qachon
 * TypeError yoki boshqa xato bermaydi, noto'g'ri qiymat kelsa berilgan standart qiymatni qaytaradi.
 *
 * Sabab: `?maydon[]=x` kabi so'rov shu maydonni massiv qilib yuboradi, lekin ko'p joyda
 * (masalan App\Models\User::scopeSearch(), App\Models\Group::scopeStatus()) parametr `?string`
 * deb e'lon qilingan - massiv kelsa PHP funksiya chaqirilishidayoq TypeError otadi (natijada 500,
 * hatto funksiya ichida try/catch bo'lsa ham, chunki xato funksiya TANASIGA kirishdan oldin sodir bo'ladi).
 * Shu klass har doim chaqiruvdan OLDIN qiymatni tekshirib, xavfsiz satr/sana/oyga aylantiradi.
 */
class SafeInput
{
    /** Har qanday qiymatni (massiv, son, satr, null) xavfsiz satrga aylantiradi. */
    public static function string(mixed $value, ?string $default = null, int $maxLength = 255): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return $default;   // massiv, obyekt va h.k. - hech qachon typed parametrga uzatilmaydi
        }

        $value = trim((string) $value);

        return $value === '' ? $default : mb_substr($value, 0, $maxLength);
    }

    /** Sana matnini tekshiradi (Carbon tushunadigan har qanday shakl); yaroqsiz bo'lsa $default. */
    public static function date(mixed $value, ?string $default = null): ?string
    {
        $value = self::string($value, null, 32);
        if ($value === null) {
            return $default;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (Throwable) {
            return $default;
        }
    }

    /** "YYYY-MM" oy formatini (faqat 01-12 oralig'idagi oy) tekshiradi. */
    public static function month(mixed $value, ?string $default = null): ?string
    {
        $value = self::string($value, null, 7);

        return ($value !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1) ? $value : $default;
    }
}
