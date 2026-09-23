<?php

namespace App\Enums;

enum Wallet: string
{
    case TillCash = 'till_cash';
    case TillCard = 'till_card';
    case TreasuryCash = 'treasury_cash';
    case TreasuryCard = 'treasury_card';
    /** Eski umumiy ehson hamyoni (v7 dan keyin ishlatilmaydi, qoldig'i naqt/plastikka bo'lib o'tkazilgan). */
    case TreasuryCharity = 'treasury_charity';
    case TreasuryCharityCash = 'treasury_charity_cash';
    case TreasuryCharityCard = 'treasury_charity_card';

    public function label(): string
    {
        return match ($this) {
            self::TillCash => 'Kassa (naqt)',
            self::TillCard => 'Kassa (plastik)',
            self::TreasuryCash => 'Moliya balansi (naqt)',
            self::TreasuryCard => 'Moliya balansi (plastik)',
            self::TreasuryCharity => 'Ehson balansi (eski)',
            self::TreasuryCharityCash => 'Ehson balansi (naqt)',
            self::TreasuryCharityCard => 'Ehson balansi (plastik)',
        };
    }
}
