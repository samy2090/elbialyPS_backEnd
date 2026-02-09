<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpinWheelClaim extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'batch_id',
        'option_id',
        'reward_type',
        'reward_value',
        'status',
        'fulfilled_by',
        'fulfilled_at',
        'fulfillment_notes',
    ];

    protected $casts = [
        'reward_value' => 'array',
        'fulfilled_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public const STATUS_GRANTED = 'granted';   // points - immediately applied
    public const STATUS_PENDING = 'pending';   // non-points - waiting admin fulfillment
    public const STATUS_FULFILLED = 'fulfilled'; // admin marked done

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SpinWheelUserBatch::class, 'batch_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(SpinWheelOption::class);
    }

    public function fulfilledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }

    public function needsFulfillment(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
