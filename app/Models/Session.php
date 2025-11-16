<?php

namespace App\Models;

use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Session extends Model
{
    use HasFactory;

    protected $table = 'game_sessions';

    protected $fillable = [
        'created_by',
        'customer_id',
        'started_at',
        'ended_at',
        'status',
        'total_price',
        'discount',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'total_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'status' => SessionStatus::class,
    ];

    /**
     * Get the user who created this session
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this session
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the customer associated with this session
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Get all users in this session
     */
    public function sessionUsers(): HasMany
    {
        return $this->hasMany(SessionUser::class);
    }

    /**
     * Get all activities in this session
     */
    public function activities(): HasMany
    {
        return $this->hasMany(SessionActivity::class);
    }

    /**
     * Check if session is active
     */
    public function isActive(): bool
    {
        return $this->status === SessionStatus::ACTIVE;
    }

    /**
     * Check if session is paused
     */
    public function isPaused(): bool
    {
        return $this->status === SessionStatus::PAUSED;
    }

    /**
     * Check if session is ended
     */
    public function isEnded(): bool
    {
        return $this->status === SessionStatus::ENDED;
    }
}
