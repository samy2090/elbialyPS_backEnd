<?php

namespace App\Enums;

enum UserRole: string
{
    case GUEST = 'guest';
    case CUSTOMER = 'customer';
    case ADMIN = 'admin';
    case STAFF = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::GUEST => 'Guest',
            self::CUSTOMER => 'Customer',
            self::ADMIN => 'Admin',
            self::STAFF => 'Staff',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($case) => [$case->value => $case->label()])
            ->toArray();
    }
}