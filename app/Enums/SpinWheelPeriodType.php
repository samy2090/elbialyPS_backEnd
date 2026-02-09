<?php

namespace App\Enums;

enum SpinWheelPeriodType: string
{
    case EVERY_N_DAYS = 'every_n_days';      // Calendar: every 1/2/3/5/7 days from start_date
    case EVERY_WEEKDAY = 'every_weekday';    // Every Mon/Tue/.../Sun (start_date = first occurrence)
    case EVERY_MONTH = 'every_month';        // 1st to last of each month
    case COOLDOWN_DAYS = 'cooldown_days';    // X days cooldown from when user claimed

    public function label(): string
    {
        return match ($this) {
            self::EVERY_N_DAYS => 'Every N Days (calendar)',
            self::EVERY_WEEKDAY => 'Every Weekday',
            self::EVERY_MONTH => 'Every Month',
            self::COOLDOWN_DAYS => 'Cooldown (days from claim)',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
