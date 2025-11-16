<?php

namespace App\Enums;

enum ActivityType: string
{
    case DEVICE_USE = 'device_use';
    case PAUSE = 'pause';

    public function label(): string
    {
        return match ($this) {
            self::DEVICE_USE => 'Device Use',
            self::PAUSE => 'Pause',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DEVICE_USE => 'blue',
            self::PAUSE => 'orange',
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

    public function isDeviceUse(): bool
    {
        return $this === self::DEVICE_USE;
    }

    public function isPause(): bool
    {
        return $this === self::PAUSE;
    }

    public function requiresDevice(): bool
    {
        return $this === self::DEVICE_USE;
    }
}
