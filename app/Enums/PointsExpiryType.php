<?php

namespace App\Enums;

enum PointsExpiryType: string
{
    case MONTHLY = 'monthly';
    case SPECIFIC_DATE = 'specific_date';

    public function label(): string
    {
        return match ($this) {
            self::MONTHLY => 'Monthly',
            self::SPECIFIC_DATE => 'Specific Date',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
