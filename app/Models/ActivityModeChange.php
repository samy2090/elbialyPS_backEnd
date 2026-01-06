<?php

namespace App\Models;

use App\Enums\ActivityMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityModeChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_activity_id',
        'from_mode',
        'to_mode',
        'changed_at',
        'ended_at',
        'duration_minutes',
        'changed_by',
        'notes',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_minutes' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the session activity this mode change belongs to
     */
    public function sessionActivity(): BelongsTo
    {
        return $this->belongsTo(SessionActivity::class);
    }

    /**
     * Get the user who changed the mode
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Check if mode change period is still active (not ended yet)
     */
    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * Calculate and update mode duration
     */
    public function calculateDuration(): void
    {
        if ($this->ended_at && $this->changed_at) {
            // Calculate duration: ended_at - changed_at (in minutes)
            $this->duration_minutes = abs($this->changed_at->diffInMinutes($this->ended_at));
            $this->save();
        }
    }

    /**
     * Get to_mode as ActivityMode enum
     */
    public function getToModeEnum(): ActivityMode
    {
        return ActivityMode::from($this->to_mode);
    }

    /**
     * Get from_mode as ActivityMode enum (nullable)
     */
    public function getFromModeEnum(): ?ActivityMode
    {
        return $this->from_mode ? ActivityMode::from($this->from_mode) : null;
    }
}
