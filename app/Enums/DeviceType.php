<?php

namespace App\Enums;

enum DeviceType: string
{
    case PS4 = 'ps4';
    case PS5 = 'ps5';
    case BILLBOARD = 'billboard';

    public function label(): string
    {
        return match ($this) {
            self::PS4 => 'PlayStation 4',
            self::PS5 => 'PlayStation 5',
            self::BILLBOARD => 'Billboard',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PS4 => 'blue',
            self::PS5 => 'purple',
            self::BILLBOARD => 'green',
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

    public function isGameConsole(): bool
    {
        return in_array($this, [self::PS4, self::PS5]);
    }

    public function isBillboard(): bool
    {
        return $this === self::BILLBOARD;
    }
}