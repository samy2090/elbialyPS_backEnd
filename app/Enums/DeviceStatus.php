<?php

namespace App\Enums;

enum DeviceStatus: string
{
    case AVAILABLE = 'available';
    case IN_USE = 'in_use';
    case MAINTENANCE = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Available',
            self::IN_USE => 'In Use',
            self::MAINTENANCE => 'Maintenance',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AVAILABLE => 'green',
            self::IN_USE => 'yellow',
            self::MAINTENANCE => 'red',
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

    public function isAvailable(): bool
    {
        return $this === self::AVAILABLE;
    }

    public function isInUse(): bool
    {
        return $this === self::IN_USE;
    }

    public function isInMaintenance(): bool
    {
        return $this === self::MAINTENANCE;
    }

    public function canBeUsed(): bool
    {
        return $this === self::AVAILABLE;
    }
}