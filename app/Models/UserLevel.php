<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLevel extends Model
{
    protected $fillable = ['name', 'min_points', 'perks', 'sort_order'];

    protected $casts = [
        'min_points' => 'decimal:2',
        'perks' => 'array',
    ];

    public static function getLevelForPoints(float $points): ?self
    {
        return static::where('min_points', '<=', $points)
            ->orderBy('min_points', 'desc')
            ->first();
    }
}
