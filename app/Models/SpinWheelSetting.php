<?php

namespace App\Models;

use App\Enums\SpinWheelPeriodType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SpinWheelSetting extends Model
{
    protected $fillable = [
        'is_active',
        'period_type',
        'period_value',
        'weekday_only',
        'start_date',
        'max_spins_per_period',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'weekday_only' => 'boolean',
        'start_date' => 'date',
        'period_value' => 'integer',
        'max_spins_per_period' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('spin_wheel_settings'));
    }

    public static function getConfig(): ?self
    {
        return Cache::remember('spin_wheel_settings', 300, fn () => static::first());
    }

    public function periodTypeEnum(): ?SpinWheelPeriodType
    {
        return SpinWheelPeriodType::tryFrom($this->period_type);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }
}
