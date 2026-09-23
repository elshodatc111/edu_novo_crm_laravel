<?php

namespace App\Support;

/**
 * v8 A5: matn ichidan "parol: XXXXXXXX" ko'rinishidagi ochiq parolni taxminiy topib berkitadi.
 * Faqat ESKI (migratsiyadan oldingi) SMS yozuvlarini bir martalik tozalash uchun ishlatiladi -
 * yangi xabarlar uchun SmsNotifier haqiqiy o'zgaruvchilar orqali aniq (heuristikasiz) berkitadi.
 */
class SmsPasswordMasker
{
    public static function mask(string $text): string
    {
        return preg_replace('/(parol[^:]{0,20}:\s*)([A-Za-z0-9]{6,12})\b/iu', '$1••••••••', $text) ?? $text;
    }
}
