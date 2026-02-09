<?php

namespace App\Enums;

enum SpinWheelRewardType: string
{
    case POINTS = 'points';
    case PERCENT_DISCOUNT = 'percent_discount';
    case FREE_MINUTES = 'free_minutes';
    case FREE_PRODUCT = 'free_product';

    public function label(): string
    {
        return match ($this) {
            self::POINTS => 'Points',
            self::PERCENT_DISCOUNT => 'Percentage Off',
            self::FREE_MINUTES => 'Free Minutes',
            self::FREE_PRODUCT => 'Free Product',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
