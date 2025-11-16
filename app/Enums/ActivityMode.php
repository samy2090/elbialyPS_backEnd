<?php

namespace App\Enums;

enum ActivityMode: string
{
    case SINGLE = 'single';
    case MULTI = 'multi';

    public function label(): string
    {
        return match ($this) {
            self::SINGLE => 'Single Player',
            self::MULTI => 'Multiplayer',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SINGLE => 'purple',
            self::MULTI => 'cyan',
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

    public function isSingle(): bool
    {
        return $this === self::SINGLE;
    }

    public function isMulti(): bool
    {
        return $this === self::MULTI;
    }
}
