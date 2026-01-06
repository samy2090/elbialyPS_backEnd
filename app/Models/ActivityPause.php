<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityPause extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_activity_id',
        'paused_at',
        'resumed_at',
        'pause_duration_minutes',
        'paused_by',
        'resumed_by',
        'notes',
    ];

    protected $casts = [
        'paused_at' => 'datetime',
        'resumed_at' => 'datetime',
        'pause_duration_minutes' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the session activity this pause belongs to
     */
    public function sessionActivity(): BelongsTo
    {
        return $this->belongsTo(SessionActivity::class);
    }

    /**
     * Get the user who paused the activity
     */
    public function pausedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paused_by');
    }

    /**
     * Get the user who resumed the activity
     */
    public function resumedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resumed_by');
    }

    /**
     * Check if pause is still active (not resumed yet)
     */
    public function isActive(): bool
    {
        return $this->resumed_at === null;
    }

    /**
     * Calculate and update pause duration
     */
    public function calculateDuration(): void
    {
        if ($this->resumed_at && $this->paused_at) {
            // Calculate duration: resumed_at - paused_at (in minutes)
            // diffInMinutes returns absolute difference, so order doesn't matter
            $this->pause_duration_minutes = abs($this->paused_at->diffInMinutes($this->resumed_at));
            $this->save();
        }
    }
}
