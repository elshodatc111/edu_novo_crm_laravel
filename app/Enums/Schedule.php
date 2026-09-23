<?php

namespace App\Enums;

enum Schedule: string
{
    case Odd = 'odd';
    case Even = 'even';
    case Daily = 'daily';

    public function label(): string
    {
        return match ($this) {
            self::Odd => 'Toq kunlar (Du-Chor-Ju)',
            self::Even => 'Juft kunlar (Se-Pay-Sha)',
            self::Daily => 'Har kuni (Du-Sha)',
        };
    }

    /** ISO hafta kunlari: 1 - dushanba ... 7 - yakshanba */
    public function weekdays(): array
    {
        return match ($this) {
            self::Odd => [1, 3, 5],
            self::Even => [2, 4, 6],
            self::Daily => [1, 2, 3, 4, 5, 6],
        };
    }
}
