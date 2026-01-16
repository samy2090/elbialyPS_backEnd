<?php

namespace App\Models;

use App\Enums\SessionStatus;
use App\Enums\SessionType;
use App\Models\ActivityProduct;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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
        'type',
        'total_price',
        'discount',
        'updated_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'total_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'status' => SessionStatus::class,
        'type' => SessionType::class,
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
     * Get all activities in this session
     */
    public function activities(): HasMany
    {
        return $this->hasMany(SessionActivity::class);
    }

    /**
     * Get all users in this session through activities (HasManyThrough relation)
     */
    public function sessionUsers()
    {
        return $this->hasManyThrough(
            ActivityUser::class,
            SessionActivity::class,
            'session_id',      // Foreign key on session_activities table
            'session_activity_id',  // Foreign key on activity_user table
            'id',             // Local key on sessions table
            'id'              // Local key on session_activities table
        );
    }

    /**
     * Get all unique users in this session (collection of User objects)
     */
    public function getSessionUsers()
    {
        return $this->sessionUsers()
            ->with('user')
            ->get()
            ->groupBy('user_id')
            ->map(function ($group) {
                return $group->first();
            })
            ->values();
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

    /**
     * Calculate and update the total price of the session
     * Total = Sum of all activities' total_price
     * Note: Each activity's total_price already includes:
     *   - Device usage (based on actual active time and mode periods)
     *   - Products total (sum of all products in that activity)
     * So we only need to sum activities' total_price, NOT add products separately
     * Discount is stored separately and applied when calculating final price (total_price - discount)
     */
    public function calculateTotalPrice(): void
    {
        // Sum of all activities' total_price
        // Each activity's total_price already includes device usage + products
        $totalPrice = (float) ($this->activities()->sum('total_price') ?? 0);

        // Update the session's total_price without triggering events to avoid infinite loops
        // Note: discount is stored separately and should be applied when displaying final price
        $this->withoutEvents(function () use ($totalPrice) {
            $this->update(['total_price' => $totalPrice]);
        });
    }

    /**
     * Get the final price after applying discount
     * Final price = total_price - discount
     */
    public function getFinalPriceAttribute(): float
    {
        $total = (float) $this->total_price;
        $discount = (float) ($this->discount ?? 0);
        return max(0, $total - $discount); // Ensure price doesn't go negative
    }
}
