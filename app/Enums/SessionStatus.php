<?php

namespace App\Enums;

enum SessionStatus: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case ENDED = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::PAUSED => 'Paused',
            self::ENDED => 'Ended',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'green',
            self::PAUSED => 'yellow',
            self::ENDED => 'red',
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

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isPaused(): bool
    {
        return $this === self::PAUSED;
    }

    public function isEnded(): bool
    {
        return $this === self::ENDED;
    }
}
