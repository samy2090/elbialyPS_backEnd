<?php

namespace App\Enums;

enum PostStatus: string
{
    case PENDING = 'pending';
    case PUBLISHED = 'published';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PUBLISHED => 'Published',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
