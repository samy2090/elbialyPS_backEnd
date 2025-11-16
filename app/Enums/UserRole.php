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

    public function color(): string
    {
        return match ($this) {
            self::ADMIN => 'red',
            self::STAFF => 'blue',
            self::CUSTOMER => 'green',
            self::GUEST => 'gray',
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

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    public function isStaff(): bool
    {
        return $this === self::STAFF;
    }

    public function isCustomer(): bool
    {
        return $this === self::CUSTOMER;
    }

    public function isGuest(): bool
    {
        return $this === self::GUEST;
    }
}