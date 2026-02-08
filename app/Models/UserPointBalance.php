<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPointBalance extends Model
{
    protected $fillable = ['user_id', 'total_points'];

    protected $casts = [
        'total_points' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getOrCreateForUser(int $userId): self
    {
        $balance = static::firstOrCreate(
            ['user_id' => $userId],
            ['total_points' => 0]
        );

        return $balance;
    }
}
