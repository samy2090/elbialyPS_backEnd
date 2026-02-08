<?php

namespace App\Enums;

enum PointTransactionType: string
{
    case PLAY_TIME = 'play_time';
    case PRODUCT_PURCHASE = 'product_purchase';
    case PRODUCT_REFUND = 'product_refund';
    case REDEMPTION = 'redemption';
    case ADJUSTMENT = 'adjustment';
    case EXPIRY = 'expiry';

    public function label(): string
    {
        return match ($this) {
            self::PLAY_TIME => 'Play Time',
            self::PRODUCT_PURCHASE => 'Product Purchase',
            self::PRODUCT_REFUND => 'Product Refund',
            self::REDEMPTION => 'Redemption',
            self::ADJUSTMENT => 'Adjustment',
            self::EXPIRY => 'Expiry',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
