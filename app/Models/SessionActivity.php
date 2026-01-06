<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Enums\ActivityMode;
use App\Enums\DeviceStatus;
use App\Enums\SessionStatus;
use App\Enums\SessionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Models\Session;
use App\Models\ActivityPause;
use App\Models\ActivityModeChange;

class SessionActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'type',
        'activity_type',
        'device_id',
        'mode',
        'started_at',
        'ended_at',
        'status',
        'duration_hours',
        'total_price',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'duration_hours' => 'decimal:2',
        'total_price' => 'decimal:2',
        'type' => SessionType::class,
        'activity_type' => ActivityType::class,
        'mode' => ActivityMode::class,
        'status' => SessionStatus::class,
    ];

    protected $appends = ['device_name'];

    /**
     * Get the device name attribute
     */
    public function getDeviceNameAttribute(): ?string
    {
        return $this->device?->name;
    }

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        // When creating an activity with a device, mark it as in use
        static::creating(function (SessionActivity $activity) {
            // Only validate and manage device if activity_type is device_use AND device_id is provided
            if ($activity->activity_type === ActivityType::DEVICE_USE && $activity->device_id) {
                $device = Device::find($activity->device_id);
                
                // Check if device is available
                if (!$device || !$device->isAvailable()) {
                    throw new \Exception('Device is not available for use.');
                }
                
                // Check if device is already in use in another session activity
                $existingActivity = self::where('device_id', $activity->device_id)
                    ->whereNull('ended_at')
                    ->exists();
                
                if ($existingActivity) {
                    throw new \Exception('Device is already in use in another session.');
                }
                
                // Update device status to IN_USE
                $device->update(['status' => DeviceStatus::IN_USE->value]);
            }
        });

        // When deleting an activity, free up the device
        static::deleting(function (SessionActivity $activity) {
            // Only manage device if activity_type is device_use AND device_id is provided
            if ($activity->activity_type === ActivityType::DEVICE_USE && $activity->device_id) {
                $device = Device::find($activity->device_id);
                
                // Check if device has other active activities
                $hasOtherActiveActivities = self::where('device_id', $activity->device_id)
                    ->where('id', '!=', $activity->id)
                    ->whereNull('ended_at')
                    ->exists();
                
                // Only mark as AVAILABLE if no other activities are using it
                if (!$hasOtherActiveActivities && $device) {
                    $device->update(['status' => DeviceStatus::AVAILABLE->value]);
                }
            }
        });

        // When an activity is created, record initial mode and recalculate session total
        static::created(function (SessionActivity $activity) {
            // Record initial mode change
            if ($activity->mode) {
                ActivityModeChange::create([
                    'session_activity_id' => $activity->id,
                    'from_mode' => null, // Initial mode has no previous mode
                    'to_mode' => $activity->mode->value,
                    'changed_at' => $activity->started_at ?? now(),
                    'changed_by' => $activity->created_by,
                ]);
            }

            if ($activity->session_id) {
                $session = Session::find($activity->session_id);
                if ($session) {
                    $session->calculateTotalPrice();
                }
            }
        });

        // When an activity is updated (especially total_price), recalculate session total
        static::updated(function (SessionActivity $activity) {
            if ($activity->session_id && $activity->isDirty('total_price')) {
                $session = Session::find($activity->session_id);
                if ($session) {
                    $session->calculateTotalPrice();
                }
            }
        });

        // When an activity is deleted, recalculate session total
        static::deleted(function (SessionActivity $activity) {
            if ($activity->session_id) {
                $session = Session::find($activity->session_id);
                if ($session) {
                    $session->calculateTotalPrice();
                }
            }
        });
    }

    /**
     * Get the session this activity belongs to
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    /**
     * Get the device used in this activity
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Get the user who created this activity
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this activity
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get all users involved in this activity
     */
    public function activityUsers(): HasMany
    {
        return $this->hasMany(ActivityUser::class, 'session_activity_id');
    }

    /**
     * Get all users through activity_user for this activity
     */
    public function users()
    {
        return $this->hasManyThrough(
            User::class,
            ActivityUser::class,
            'session_activity_id',
            'id',
            'id',
            'user_id'
        );
    }

    /**
     * Get all products ordered during this activity
     */
    public function products(): HasMany
    {
        return $this->hasMany(ActivityProduct::class, 'session_activity_id');
    }

    /**
     * Get all pauses for this activity
     */
    public function pauses(): HasMany
    {
        return $this->hasMany(ActivityPause::class, 'session_activity_id');
    }

    /**
     * Get active pause (if activity is currently paused)
     */
    public function activePause(): ?ActivityPause
    {
        return $this->pauses()->whereNull('resumed_at')->first();
    }

    /**
     * Get all mode changes for this activity
     */
    public function modeChanges(): HasMany
    {
        return $this->hasMany(ActivityModeChange::class, 'session_activity_id');
    }

    /**
     * Get active mode change (current mode period, if not ended yet)
     */
    public function activeModeChange(): ?ActivityModeChange
    {
        return $this->modeChanges()->whereNull('ended_at')->orderBy('changed_at', 'desc')->first();
    }

    /**
     * Get total pause duration in minutes for this activity
     * Includes both completed pauses and active pauses (pauses without resumed_at)
     */
    public function getTotalPauseDurationMinutes(): float
    {
        $totalMinutes = 0;
        
        // Get all pauses for this activity
        $pauses = $this->pauses()->get();
        
        foreach ($pauses as $pause) {
            if ($pause->pause_duration_minutes !== null) {
                // Pause duration already calculated
                $totalMinutes += (float) $pause->pause_duration_minutes;
            } elseif ($pause->paused_at && $pause->resumed_at) {
                // Pause has both paused_at and resumed_at but duration not calculated yet
                $totalMinutes += abs($pause->paused_at->diffInMinutes($pause->resumed_at));
            } elseif ($pause->paused_at && !$pause->resumed_at) {
                // Active pause (not resumed yet) - calculate duration up to activity end or now
                $endTime = $this->ended_at ?? now();
                $totalMinutes += abs($pause->paused_at->diffInMinutes($endTime));
            }
        }
        
        return (float) $totalMinutes;
    }

    /**
     * Get total pause duration in hours for this activity
     */
    public function getTotalPauseDurationHours(): float
    {
        return round($this->getTotalPauseDurationMinutes() / 60, 2);
    }

    /**
     * Get the last paused_at timestamp from the active pause record
     * Returns null if activity is not currently paused
     */
    public function getLastPausedAt(): ?\Carbon\Carbon
    {
        $activePause = $this->activePause();
        return $activePause ? $activePause->paused_at : null;
    }

    /**
     * Check if activity is currently running (active and not ended)
     */
    public function isRunning(): bool
    {
        return $this->status === SessionStatus::ACTIVE && $this->ended_at === null;
    }

    /**
     * Check if activity status is paused (paused and not ended)
     */
    public function hasPausedStatus(): bool
    {
        return $this->status === SessionStatus::PAUSED && $this->ended_at === null;
    }

    /**
     * Check if activity is device use type
     */
    public function isDeviceUse(): bool
    {
        return $this->activity_type === ActivityType::DEVICE_USE;
    }

    /**
     * Check if activity is pause type
     */
    public function isPause(): bool
    {
        return $this->activity_type === ActivityType::PAUSE;
    }

    /**
     * Check if activity is in single mode
     */
    public function isSingleMode(): bool
    {
        return $this->mode === ActivityMode::SINGLE;
    }

    /**
     * Check if activity is in multi mode
     */
    public function isMultiMode(): bool
    {
        return $this->mode === ActivityMode::MULTI;
    }

    /**
     * End this activity and free up the device
     */
    public function end(): self
    {
        $this->update(['ended_at' => now()]);
        
        // If activity is device_use and has device_id, update device status back to AVAILABLE
        if ($this->isDeviceUse() && $this->device_id) {
            $device = Device::find($this->device_id);
            
            // Check if device has other active activities in this session
            $hasOtherActiveActivities = self::where('session_id', $this->session_id)
                ->where('device_id', $this->device_id)
                ->where('id', '!=', $this->id)
                ->whereNull('ended_at')
                ->exists();
            
            // Only mark as AVAILABLE if no other activities are using it
            if (!$hasOtherActiveActivities && $device) {
                $device->update(['status' => DeviceStatus::AVAILABLE->value]);
            }
        }
        
        return $this;
    }

    /**
     * Scope: Filter by activity type
     */
    public function scopeOfType($query, ActivityType|string $type)
    {
        $value = $type instanceof ActivityType ? $type->value : $type;
        return $query->where('activity_type', $value);
    }

    /**
     * Scope: Filter by mode
     */
    public function scopeOfMode($query, ActivityMode|string $mode)
    {
        $value = $mode instanceof ActivityMode ? $mode->value : $mode;
        return $query->where('mode', $value);
    }

    /**
     * Scope: Get active activities (not ended)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }

    /**
     * Scope: Get ended activities
     */
    public function scopeEnded($query)
    {
        return $query->whereNotNull('ended_at');
    }

    /**
     * Scope: Filter by device
     */
    public function scopeForDevice($query, $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }
}
