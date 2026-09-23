<?php

namespace App\Enums;

enum Role: string
{
    case SAdmin = 'sadmin';
    case Admin = 'admin';
    case Manager = 'manager';
    case Teacher = 'teacher';
    case Operator = 'operator';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::SAdmin => 'sAdmin',
            self::Admin => 'Admin',
            self::Manager => 'Menejer',
            self::Teacher => "O'qituvchi",
            self::Operator => 'Operator',
            self::Student => "O'quvchi",
        };
    }

    /** Veb-panelga kira oladigan rollar. */
    public function canUsePanel(): bool
    {
        return $this !== self::Student;
    }

    /** Ruxsatlari alohida belgilanadigan rollar. */
    public function hasConfigurablePermissions(): bool
    {
        return in_array($this, [self::Admin, self::Manager, self::Teacher, self::Operator], true);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::SAdmin => 'badge-red',
            self::Admin => 'badge-dark',
            self::Manager => 'badge-blue',
            self::Teacher => 'badge-amber',
            self::Operator => 'badge-purple',
            self::Student => 'badge-gray',
        };
    }
}
