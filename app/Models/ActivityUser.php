<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityUser extends Model
{
    use HasFactory;

    protected $table = 'activity_user';

    protected $fillable = [
        'session_activity_id',
        'user_id',
        'duration_hours',
        'cost_share',
    ];

    protected $casts = [
        'duration_hours' => 'decimal:2',
        'cost_share' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['duration_formatted'];

    /**
     * Format duration_hours as "H:MM minutes" for display
     * Example: 0.5 hours -> "0:30 minutes", 1.5 hours -> "1:30 minutes", 2.0 hours -> "2:00 minutes"
     */
    public function getDurationFormattedAttribute(): ?string
    {
        if ($this->duration_hours === null) {
            return null;
        }

        $hours = (int) floor($this->duration_hours);
        $minutes = (int) round(($this->duration_hours - $hours) * 60);
        
        // Handle edge case where minutes round to 60
        if ($minutes >= 60) {
            $hours += 1;
            $minutes = 0;
        }

        return sprintf('%d:%02d minutes', $hours, $minutes);
    }

    /**
     * Get the session activity this belongs to
     */
    public function sessionActivity(): BelongsTo
    {
        return $this->belongsTo(SessionActivity::class);
    }

    /**
     * Get the user this belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
