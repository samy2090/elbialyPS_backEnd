<?php

namespace App\Enums;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

enum FinancialPeriod: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function defaultRange(CarbonInterface $now): array
    {
        $end = $now->copy()->endOfDay();

        $start = match ($this) {
            self::Daily   => $now->copy()->subDays(29)->startOfDay(),
            self::Weekly  => $now->copy()->subWeeks(11)->startOfWeek(CarbonInterface::SATURDAY),
            self::Monthly => $now->copy()->subMonths(11)->startOfMonth(),
            self::Yearly  => $now->copy()->subYears(4)->startOfYear(),
        };

        return [$start, $end];
    }

    /**
     * Build a MySQL expression that buckets the given datetime column to the
     * start of its period. Week starts Saturday (Egypt business week).
     *
     * DAYOFWEEK() returns 1=Sun..7=Sat. To shift week start to Saturday,
     * we compute days-from-saturday = (DAYOFWEEK - 7 + 7) % 7 = (DAYOFWEEK) % 7.
     * That maps Sat(7)->0, Sun(1)->1, Mon(2)->2, ..., Fri(6)->6.
     */
    public function bucketExpression(string $column): string
    {
        return match ($this) {
            self::Daily   => "DATE({$column})",
            self::Weekly  => "DATE_SUB(DATE({$column}), INTERVAL (DAYOFWEEK({$column}) % 7) DAY)",
            self::Monthly => "DATE_FORMAT({$column}, '%Y-%m-01')",
            self::Yearly  => "DATE_FORMAT({$column}, '%Y-01-01')",
        };
    }

    public function startOf(CarbonInterface $date): CarbonInterface
    {
        return match ($this) {
            self::Daily   => $date->copy()->startOfDay(),
            self::Weekly  => $date->copy()->startOfWeek(CarbonInterface::SATURDAY),
            self::Monthly => $date->copy()->startOfMonth(),
            self::Yearly  => $date->copy()->startOfYear(),
        };
    }

    public function endOf(CarbonInterface $date): CarbonInterface
    {
        return match ($this) {
            self::Daily   => $date->copy()->endOfDay(),
            self::Weekly  => $date->copy()->endOfWeek(CarbonInterface::FRIDAY),
            self::Monthly => $date->copy()->endOfMonth(),
            self::Yearly  => $date->copy()->endOfYear(),
        };
    }

    public function next(CarbonInterface $date): CarbonInterface
    {
        return match ($this) {
            self::Daily   => $date->copy()->addDay(),
            self::Weekly  => $date->copy()->addWeek(),
            self::Monthly => $date->copy()->addMonth(),
            self::Yearly  => $date->copy()->addYear(),
        };
    }

    public function label(CarbonInterface $start): string
    {
        return match ($this) {
            self::Daily   => $start->format('Y-m-d'),
            self::Weekly  => $start->format('Y-m-d'),
            self::Monthly => $start->format('Y-m'),
            self::Yearly  => $start->format('Y'),
        };
    }
}
