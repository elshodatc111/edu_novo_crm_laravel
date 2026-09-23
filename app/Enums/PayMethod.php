<?php

namespace App\Enums;

enum PayMethod: string
{
    case Cash = 'cash';
    case Card = 'card';

    public function label(): string
    {
        return $this === self::Cash ? 'Naqt' : 'Plastik';
    }

    public function till(): Wallet
    {
        return $this === self::Cash ? Wallet::TillCash : Wallet::TillCard;
    }

    public function treasury(): Wallet
    {
        return $this === self::Cash ? Wallet::TreasuryCash : Wallet::TreasuryCard;
    }

    public function charity(): Wallet
    {
        return $this === self::Cash ? Wallet::TreasuryCharityCash : Wallet::TreasuryCharityCard;
    }
}
