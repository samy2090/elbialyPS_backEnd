<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Enums\ActivityMode;
use App\Enums\DeviceStatus;
use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class SessionActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'activity_type',
        'device_id',
        'mode',
        'started_at',
        'ended_at',
        'status',
        'duration_hours',
        'price_per_hour',
        'total_price',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'duration_hours' => 'decimal:2',
        'price_per_hour' => 'decimal:2',
        'total_price' => 'decimal:2',
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
            if ($activity->device_id) {
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
        
        // If activity is device_use, update device status back to AVAILABLE
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
