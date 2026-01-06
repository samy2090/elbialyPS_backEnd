<?php

namespace App\Services;

use App\Models\SessionActivity;
use App\Models\ActivityUser;
use App\Models\Device;
use App\Models\ActivityModeChange;
use App\Enums\SessionStatus;
use App\Enums\DeviceStatus;
use App\Enums\ActivityMode;
use App\Repositories\SessionActivityRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SessionActivityService
{
    private SessionActivityRepositoryInterface $sessionActivityRepository;

    public function __construct(SessionActivityRepositoryInterface $sessionActivityRepository)
    {
        $this->sessionActivityRepository = $sessionActivityRepository;
    }

    public function getSessionActivities(int $sessionId): Collection
    {
        return $this->sessionActivityRepository->getBySessionId($sessionId);
    }

    public function getActivity(int $id): ?SessionActivity
    {
        return $this->sessionActivityRepository->getById($id);
    }

    public function createActivity(array $data): SessionActivity
    {
        return $this->sessionActivityRepository->create($data);
    }

    public function updateActivity(int $id, array $data): bool
    {
        $activity = $this->getActivity($id);
        if (!$activity) {
            return false;
        }

        // Track mode change if mode is being updated
        if (isset($data['mode']) && $activity->mode) {
            $newMode = is_string($data['mode']) 
                ? ActivityMode::from($data['mode']) 
                : $data['mode'];
            
            // Only track if mode actually changed
            if ($activity->mode !== $newMode) {
                $changeTime = now();
                
                // End the current active mode change period
                $activeModeChange = $activity->activeModeChange();
                if ($activeModeChange) {
                    $activeModeChange->update([
                        'ended_at' => $changeTime,
                    ]);
                    $activeModeChange->calculateDuration();
                }
                
                // Create new mode change record
                ActivityModeChange::create([
                    'session_activity_id' => $activity->id,
                    'from_mode' => $activity->mode->value,
                    'to_mode' => $newMode->value,
                    'changed_at' => $changeTime,
                    'changed_by' => auth()->id(),
                ]);
            }
        }

        // Model events will automatically recalculate session total when total_price is updated
        return $this->sessionActivityRepository->update($id, $data);
    }

    public function deleteActivity(int $id): bool
    {
        return $this->sessionActivityRepository->delete($id);
    }

    public function getActivitiesByType(string $type, int $perPage = 10): LengthAwarePaginator
    {
        return $this->sessionActivityRepository->getByActivityType($type, $perPage);
    }

    public function endActivity(int $id, int $sessionId, array $data): bool
    {
        // Validate that activity belongs to the session
        $activity = $this->sessionActivityRepository->getByIdAndSessionId($id, $sessionId);
        if (!$activity) {
            return false;
        }
        
        // Validation: If activity status is already ended, do nothing
        if ($activity->status === \App\Enums\SessionStatus::ENDED) {
            return true; // Already ended, return success
        }
        
        $endTime = now();
        $activityEndTime = null;
        
        // Determine end time based on activity state
        if ($activity->isRunning()) {
            // Activity is running: end_at = now
            $activityEndTime = $endTime;
        } elseif ($activity->hasPausedStatus()) {
            // Activity is paused: end_at = last_paused_at
            $lastPausedAt = $activity->getLastPausedAt();
            if ($lastPausedAt) {
                $activityEndTime = $lastPausedAt;
            } else {
                // Fallback: if no pause record found, use now
                $activityEndTime = $endTime;
            }
        } else {
            // Fallback: use now
            $activityEndTime = $endTime;
        }
        
        // If activity is currently paused, complete the active pause record
        $activePause = $activity->activePause();
        if ($activePause) {
            $activePause->update([
                'resumed_at' => $activityEndTime,
            ]);
            $activePause->calculateDuration();
        }
        
        // Complete the active mode change period (if any)
        $activeModeChange = $activity->activeModeChange();
        if ($activeModeChange) {
            $activeModeChange->update([
                'ended_at' => $activityEndTime,
            ]);
            $activeModeChange->calculateDuration();
        }
        
        // Update activity
        $updateData = [
            'ended_at' => $activityEndTime,
            'status' => 'ended',
        ];
        
        $updated = $this->sessionActivityRepository->update($id, $updateData);
        
        // Calculate duration with pause time excluded and save to duration_hours field
        if ($updated) {
            $activity->refresh();
            if ($activity->started_at && $activity->ended_at) {
                $this->calculateDuration($id);
            }
            
            // Free up the device if activity uses a device
            if ($activity->isDeviceUse() && $activity->device_id) {
                $device = Device::find($activity->device_id);
                
                if ($device) {
                    // Check if device has other active activities (not ended)
                    $hasOtherActiveActivities = SessionActivity::where('device_id', $activity->device_id)
                        ->where('id', '!=', $activity->id)
                        ->where('status', '!=', SessionStatus::ENDED->value)
                        ->whereNull('ended_at')
                        ->exists();
                    
                    // Only mark as AVAILABLE if no other activities are using it
                    if (!$hasOtherActiveActivities) {
                        $device->update(['status' => DeviceStatus::AVAILABLE->value]);
                    }
                }
            }
        }
        
        // Check if this was the last active/paused activity in the session
        // If so, automatically end the session
        if ($updated) {
            $remainingActivities = $this->sessionActivityRepository->getBySessionId($sessionId)
                ->filter(function ($act) {
                    // Check if activity is not ended (active or paused)
                    return $act->ended_at === null;
                });
            
            // If no remaining active/paused activities, end the session
            if ($remainingActivities->isEmpty()) {
                // Use app() to resolve SessionService to avoid circular dependency issues
                $sessionService = app(SessionService::class);
                $session = $sessionService->getSession($sessionId);
                
                // Only end the session if it's not already ended
                if ($session && $session->status !== \App\Enums\SessionStatus::ENDED) {
                    $sessionService->endSession($sessionId, ['confirm_end_activities' => true]);
                }
            }
        }
        
        // Model events will handle session total recalculation automatically
        return $updated;
    }

    public function calculateDuration(int $id): bool
    {
        $activity = $this->getActivity($id);
        if (!$activity || !$activity->started_at || !$activity->ended_at) {
            return false;
        }

        // Refresh activity to get latest data
        $activity->refresh();
        $activity->load(['pauses', 'modeChanges', 'products', 'device']);

        // Calculate total time in minutes for better precision, then convert to hours
        $totalTimeInMinutes = abs($activity->ended_at->diffInMinutes($activity->started_at));
        $totalTimeInHours = $totalTimeInMinutes / 60;
        
        // Get total pause duration in hours
        $totalPauseDurationHours = $activity->getTotalPauseDurationHours();
        
        // Calculate real activity duration (excluding pause time)
        $realDurationHours = $totalTimeInHours - $totalPauseDurationHours;
        
        // Ensure duration is not negative
        $realDurationHours = max(0, $realDurationHours);

        // Calculate device usage price based on mode periods
        $deviceUsagePrice = 0;
        
        if ($activity->isDeviceUse() && $activity->device_id) {
            // Get device prices (in Egyptian Pounds)
            $device = $activity->device;
            $singlePricePerHour = (float) $device->price_per_hour;
            $multiPricePerHour = $device->multi_price 
                ? (float) $device->multi_price
                : $singlePricePerHour;

            // Get all mode changes ordered by changed_at
            $modeChanges = $activity->modeChanges()->orderBy('changed_at', 'asc')->get();
            
            $singleHours = 0;
            $multiHours = 0;

            if ($modeChanges->isEmpty()) {
                // No mode changes recorded - use current mode for entire duration
                // This handles edge case where mode changes weren't tracked
                if ($activity->mode === ActivityMode::SINGLE) {
                    $singleHours = $realDurationHours;
                } else {
                    $multiHours = $realDurationHours;
                }
            } else {
                // Calculate time for each mode period, accounting for pauses
                foreach ($modeChanges as $modeChange) {
                    $periodStart = $modeChange->changed_at;
                    $periodEnd = $modeChange->ended_at ?? $activity->ended_at;
                    
                    // Calculate raw period duration in minutes
                    $periodDurationMinutes = abs($periodStart->diffInMinutes($periodEnd));
                    
                    // Calculate pause overlap with this period
                    // Get all pauses and check overlap in PHP for better accuracy
                    $pauseMinutesInPeriod = 0;
                    foreach ($activity->pauses as $pause) {
                        $pauseStart = $pause->paused_at;
                        $pauseEnd = $pause->resumed_at ?? $activity->ended_at;
                        
                        // Check if pause overlaps with period
                        // Overlap exists if pause starts before period ends and pause ends after period starts
                        if ($pauseStart < $periodEnd && $pauseEnd > $periodStart) {
                            // Calculate overlap between pause and period
                            $overlapStart = $pauseStart > $periodStart ? $pauseStart : $periodStart;
                            $overlapEnd = $pauseEnd < $periodEnd ? $pauseEnd : $periodEnd;
                            
                            if ($overlapStart < $overlapEnd) {
                                $pauseMinutesInPeriod += abs($overlapStart->diffInMinutes($overlapEnd));
                            }
                        }
                    }
                    
                    // Active duration for this mode period (excluding pauses)
                    $activeMinutes = max(0, $periodDurationMinutes - $pauseMinutesInPeriod);
                    $activeHours = $activeMinutes / 60;
                    
                    // Add to appropriate mode counter
                    $modeEnum = ActivityMode::from($modeChange->to_mode);
                    if ($modeEnum === ActivityMode::SINGLE) {
                        $singleHours += $activeHours;
                    } else {
                        $multiHours += $activeHours;
                    }
                }
            }

            // Calculate device usage price
            $deviceUsagePrice = ($singleHours * $singlePricePerHour) + ($multiHours * $multiPricePerHour);
        }

        // Calculate products total for this activity
        $productsTotal = (float) ($activity->products()->sum('total_price') ?? 0);

        // Total activity price = device usage + products
        $totalPrice = $deviceUsagePrice + $productsTotal;

        // Model events will automatically recalculate session total when total_price is updated
        return $this->sessionActivityRepository->update($id, [
            'duration_hours' => round($realDurationHours, 2),
            'total_price' => round($totalPrice, 2),
        ]);
    }

    /**
     * Add a user to a session activity
     */
    public function addUserToActivity(int $activityId, array $data): ActivityUser
    {
        $data['session_activity_id'] = $activityId;
        return ActivityUser::create($data);
    }

    /**
     * Remove a user from a session activity
     */
    public function removeUserFromActivity(int $activityId, int $userId): bool
    {
        return ActivityUser::where('session_activity_id', $activityId)
            ->where('user_id', $userId)
            ->delete() > 0;
    }

    /**
     * Get users in an activity
     */
    public function getActivityUsers(int $activityId): Collection
    {
        return ActivityUser::where('session_activity_id', $activityId)
            ->with('user')
            ->get();
    }

    /**
     * Update activity status with proper business logic
     */
    public function updateActivityStatus(int $id, int $sessionId, array $data): ?SessionActivity
    {
        // Validate that activity belongs to the session
        $activity = $this->sessionActivityRepository->getByIdAndSessionId($id, $sessionId);
        
        if (!$activity) {
            return null;
        }

        $newStatus = SessionStatus::from($data['status']);
        $oldStatus = $activity->status;

        // If status is already the same, return the activity
        if ($newStatus === $oldStatus) {
            return $activity;
        }

        // Handle status transitions with business logic
        $updateData = ['status' => $newStatus->value];

        // If changing to ENDED, set ended_at timestamp
        if ($newStatus === SessionStatus::ENDED && !$activity->ended_at) {
            $endTime = now();
            $activityEndTime = null;
            
            // Determine end time based on activity state
            if ($activity->isRunning()) {
                // Activity is running: end_at = now
                $activityEndTime = $endTime;
            } elseif ($activity->hasPausedStatus()) {
                // Activity is paused: end_at = last_paused_at
                $lastPausedAt = $activity->getLastPausedAt();
                if ($lastPausedAt) {
                    $activityEndTime = $lastPausedAt;
                } else {
                    // Fallback: if no pause record found, use now
                    $activityEndTime = $endTime;
                }
            } else {
                // Fallback: use now
                $activityEndTime = $endTime;
            }
            
            $updateData['ended_at'] = $activityEndTime;
            
            // If activity is currently paused, complete the active pause record
            $activePause = $activity->activePause();
            if ($activePause) {
                $activePause->update([
                    'resumed_at' => $activityEndTime,
                ]);
                $activePause->calculateDuration();
            }

            // Complete the active mode change period (if any)
            $activeModeChange = $activity->activeModeChange();
            if ($activeModeChange) {
                $activeModeChange->update([
                    'ended_at' => $activityEndTime,
                ]);
                $activeModeChange->calculateDuration();
            }
            
            // If activity is device_use, free up the device
            if ($activity->isDeviceUse() && $activity->device_id) {
                $device = Device::find($activity->device_id);
                
                // Check if device has other active activities
                $hasOtherActiveActivities = SessionActivity::where('device_id', $activity->device_id)
                    ->where('id', '!=', $activity->id)
                    ->where('status', '!=', SessionStatus::ENDED->value)
                    ->exists();
                
                // Only mark as AVAILABLE if no other activities are using it
                if (!$hasOtherActiveActivities && $device) {
                    $device->update(['status' => DeviceStatus::AVAILABLE->value]);
                }
            }
        }

        // If changing from ENDED to another status, clear ended_at
        if ($oldStatus === SessionStatus::ENDED && $newStatus !== SessionStatus::ENDED) {
            $updateData['ended_at'] = null;
            
            // If activity is device_use and changing to active/paused, mark device as in use
            if ($activity->isDeviceUse() && $activity->device_id) {
                $device = Device::find($activity->device_id);
                
                if ($device && $device->isAvailable()) {
                    $device->update(['status' => DeviceStatus::IN_USE->value]);
                }
            }
        }

        // Handle pause/resume tracking for individual activities
        // If changing to PAUSED from ACTIVE, create pause record
        if ($newStatus === SessionStatus::PAUSED && $oldStatus === SessionStatus::ACTIVE) {
            // Only create pause record if activity is not ended and doesn't already have an active pause
            if (!$activity->ended_at && !$activity->activePause()) {
                \App\Models\ActivityPause::create([
                    'session_activity_id' => $activity->id,
                    'paused_at' => now(),
                    'paused_by' => auth()->id(),
                ]);
            }
        }

        // If changing to ACTIVE from PAUSED, complete the active pause record
        if ($newStatus === SessionStatus::ACTIVE && $oldStatus === SessionStatus::PAUSED) {
            // Find and complete the active pause record
            $activePause = $activity->activePause();
            if ($activePause) {
                $resumeTime = now();
                $activePause->update([
                    'resumed_at' => $resumeTime,
                    'resumed_by' => auth()->id(),
                ]);
                $activePause->calculateDuration();
            }
        }

        // Model events will automatically recalculate session total when total_price is updated
        $this->sessionActivityRepository->update($id, $updateData);
        
        // If activity was ended, calculate duration and save to duration_hours field
        if ($newStatus === SessionStatus::ENDED) {
            $activity->refresh();
            $activity->load('pauses');
            if ($activity->started_at && $activity->ended_at) {
                $this->calculateDuration($id);
            }
        }
        
        // Return the updated activity
        return $this->getActivity($id);
    }
}
