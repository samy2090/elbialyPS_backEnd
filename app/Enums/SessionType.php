<?php

namespace App\Enums;

enum SessionType: string
{
    case PLAYING = 'playing';
    case CHILLOUT = 'chillout';

    public function label(): string
    {
        return match ($this) {
            self::PLAYING => 'Playing',
            self::CHILLOUT => 'Chillout',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PLAYING => 'blue',
            self::CHILLOUT => 'purple',
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

    public function isPlaying(): bool
    {
        return $this === self::PLAYING;
    }

    public function isChillout(): bool
    {
        return $this === self::CHILLOUT;
    }
}

