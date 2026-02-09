<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpinWheelSpinHistory extends Model
{
    protected $table = 'spin_wheel_spin_history';

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'batch_id',
        'option_id',
        'reward_type',
        'reward_value',
        'spin_number',
        'spun_at',
    ];

    protected $casts = [
        'reward_value' => 'array',
        'spun_at' => 'datetime',
        'spin_number' => 'integer',
    ];

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
}
