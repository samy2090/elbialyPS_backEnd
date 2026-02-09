<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpinWheelUserBatch extends Model
{
    protected $table = 'spin_wheel_user_batches';

    protected $fillable = [
        'user_id',
        'period_start',
        'period_end',
        'spins_used',
        'current_result_option_id',
        'current_result_reward_data',
        'status',
        'claimed_option_id',
        'claimed_at',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'claimed_at' => 'datetime',
        'spins_used' => 'integer',
        'current_result_reward_data' => 'array',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_EXPIRED = 'expired';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentResultOption(): BelongsTo
    {
        return $this->belongsTo(SpinWheelOption::class, 'current_result_option_id');
    }

    public function claimedOption(): BelongsTo
    {
        return $this->belongsTo(SpinWheelOption::class, 'claimed_option_id');
    }

    public function spinHistory(): HasMany
    {
        return $this->hasMany(SpinWheelSpinHistory::class, 'batch_id');
    }

    public function claim(): ?SpinWheelClaim
    {
        return $this->hasOne(SpinWheelClaim::class, 'batch_id')->first();
    }

    public function canSpin(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->spins_used < config('spin_wheel.max_spins', 3);
    }

    public function canChoose(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->current_result_option_id !== null;
    }

    public function isExpired(): bool
    {
        return now(config('app.timezone'))->isAfter($this->period_end);
    }
}
