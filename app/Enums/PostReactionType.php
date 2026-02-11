<?php

namespace App\Enums;

enum PostReactionType: string
{
    case LIKE = 'like';
    case LOVE = 'love';
    case HAHA = 'haha';
    case WOW = 'wow';
    case SAD = 'sad';
    case ANGRY = 'angry';

    public function label(): string
    {
        return match ($this) {
            self::LIKE => 'Like',
            self::LOVE => 'Love',
            self::HAHA => 'Haha',
            self::WOW => 'Wow',
            self::SAD => 'Sad',
            self::ANGRY => 'Angry',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
