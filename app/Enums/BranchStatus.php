<?php

namespace App\Enums;

enum BranchStatus: string
{
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Faol',
            self::Closed => 'Yopiq (arxiv)',
        };
    }
}
