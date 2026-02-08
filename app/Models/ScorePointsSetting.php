<?php

namespace App\Models;

use App\Enums\PointsExpiryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ScorePointsSetting extends Model
{
    protected $fillable = [
        'is_active',
        'points_per_hour',
        'points_money_threshold',
        'points_per_threshold',
        'points_expiry_enabled',
        'points_expiry_type',
        'points_expiry_day_of_month',
        'points_expiry_specific_date',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'points_per_hour' => 'decimal:2',
        'points_money_threshold' => 'decimal:2',
        'points_per_threshold' => 'decimal:2',
        'points_expiry_enabled' => 'boolean',
        'points_expiry_day_of_month' => 'integer',
        'points_expiry_specific_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('score_points_settings'));
    }

    public static function getConfig(): ?self
    {
        return Cache::remember('score_points_settings', 3600, fn () => static::first());
    }

    public function isPointsSystemActive(): bool
    {
        return $this->is_active;
    }

    public function pointsExpiryType(): ?PointsExpiryType
    {
        return $this->points_expiry_type
            ? PointsExpiryType::tryFrom($this->points_expiry_type)
            : null;
    }
}
