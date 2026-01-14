<?php

namespace App\Services;

use App\Models\SessionActivity;
use App\Models\ActivityUser;
use App\Models\ActivityPause;
use App\Models\Device;
use App\Models\ActivityModeChange;
use App\Models\User;
use App\Models\Session;
use App\Enums\SessionStatus;
use App\Enums\DeviceStatus;
use App\Enums\ActivityMode;
use App\Repositories\SessionActivityRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class SessionActivityService
{
    private SessionActivityRepositoryInterface $sessionActivityRepository;

    public function __construct(SessionActivityRepositoryInterface $sessionActivityRepository)
    {
        $this->sessionActivityRepository = $sessionActivityRepository;
    }

    public function getSessionActivities(int $sessionId): Collection
    {
        // Auto-end expired activities before retrieving
        $this->autoEndExpiredActivities();
        
        return $this->sessionActivityRepository->getBySessionId($sessionId);
    }

    public function getActivity(int $id): ?SessionActivity
    {
        // Auto-end expired activities before retrieving
        $this->autoEndExpiredActivities();
        
        return $this->sessionActivityRepository->getById($id);
    }

    public function createActivity(array $data): SessionActivity
    {
        // Extract duration to calculate ended_at if provided (only for this activity)
        $duration = $data['duration'] ?? null;
        unset($data['duration']); // Remove duration from data as it's not a fillable field
        
        // Ensure started_at is set - use provided value or current timestamp
        if (!isset($data['started_at']) || empty($data['started_at'])) {
            $data['started_at'] = now();
        }
        
        // Calculate ended_at from duration if provided (only for this activity)
        if ($duration !== null && $duration > 0) {
            $startedAt = is_string($data['started_at']) ? \Carbon\Carbon::parse($data['started_at']) : $data['started_at'];
            $data['ended_at'] = $startedAt->copy()->addHours($duration);
            // Set duration_hours for this activity
            $data['duration_hours'] = $duration;
        }
        
        // Remove ended_at if it exists but duration was not provided (should not be set directly)
        if (!isset($duration) || $duration === null || $duration <= 0) {
            unset($data['ended_at']);
        }
        
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

    /**
     * Pause an activity
     */
    public function pauseActivity(int $id, int $sessionId): bool
    {
        // Validate that activity belongs to the session
        $activity = $this->sessionActivityRepository->getByIdAndSessionId($id, $sessionId);
        if (!$activity) {
            return false;
        }

        // Validation: Can only pause active activities
        if ($activity->status !== SessionStatus::ACTIVE) {
            return false;
        }

        // Only create pause record if activity is not ended and doesn't already have an active pause
        if (!$activity->ended_at && !$activity->activePause()) {
            ActivityPause::create([
                'session_activity_id' => $activity->id,
                'paused_at' => now(),
                'paused_by' => auth()->id(),
            ]);
        }

        // Update activity status to paused
        return $this->sessionActivityRepository->update($id, ['status' => SessionStatus::PAUSED->value]);
    }

    /**
     * Resume a paused activity
     */
    public function resumeActivity(int $id, int $sessionId): bool
    {
        // Validate that activity belongs to the session
        $activity = $this->sessionActivityRepository->getByIdAndSessionId($id, $sessionId);
        if (!$activity) {
            return false;
        }

        // Validation: Can only resume paused activities
        if ($activity->status !== SessionStatus::PAUSED) {
            return false;
        }

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

        // Update activity status to active
        return $this->sessionActivityRepository->update($id, ['status' => SessionStatus::ACTIVE->value]);
    }

    /**
     * Private helper: Perform the core logic for ending an activity
     * Returns the activity end time that was determined
     */
    private function performEndActivityLogic(SessionActivity $activity, ?\Carbon\Carbon $endTime = null): ?\Carbon\Carbon
    {
        if ($endTime === null) {
            $endTime = now();
        }

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
                // Fallback: if no pause record found, use provided end time
                $activityEndTime = $endTime;
            }
        } else {
            // Fallback: use provided end time
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
            'status' => SessionStatus::ENDED->value,
        ];
        
        $updated = $this->sessionActivityRepository->update($activity->id, $updateData);
        
        if (!$updated) {
            return null;
        }

        // Calculate duration with pause time excluded and save to duration_hours field
        $activity->refresh();
        if ($activity->started_at && $activity->ended_at) {
            $this->calculateDuration($activity->id);
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

        return $activityEndTime;
    }

    /**
     * Private helper: Check if session should be ended and end it if needed
     */
    private function checkAndEndSessionIfNeeded(int $sessionId): void
    {
        // Query directly from database to ensure we get fresh data after the update
        // This ensures the just-updated activity is included with its new ENDED status
        $remainingActivitiesCount = SessionActivity::where('session_id', $sessionId)
            ->where('status', '!=', SessionStatus::ENDED->value)
            ->count();
        
        // If no remaining active/paused activities, end the session
        if ($remainingActivitiesCount === 0) {
            // Use app() to resolve SessionService to avoid circular dependency issues
            $sessionService = app(SessionService::class);
            $session = $sessionService->getSession($sessionId);
            
            // Only end the session if it's not already ended
            if ($session && $session->status !== SessionStatus::ENDED) {
                $sessionService->endSession($sessionId, ['confirm_end_activities' => true]);
            }
        }
    }

    /**
     * End an activity
     */
    public function endActivity(int $id, int $sessionId, array $data = []): bool
    {
        // Validate that activity belongs to the session
        $activity = $this->sessionActivityRepository->getByIdAndSessionId($id, $sessionId);
        if (!$activity) {
            return false;
        }
        
        // Validation: If activity status is already ended, do nothing
        if ($activity->status === SessionStatus::ENDED) {
            return true; // Already ended, return success
        }
        
        // Perform the end logic
        $activityEndTime = $this->performEndActivityLogic($activity);
        
        if ($activityEndTime === null) {
            return false;
        }
        
        // Check if session should be ended
        $this->checkAndEndSessionIfNeeded($sessionId);
        
        // Model events will handle session total recalculation automatically
        return true;
    }

    /**
     * Calculate duration and total price for an activity
     * Uses ended_at timestamp (not current time) - for activities that have ended
     */
    public function calculateDuration(int $id): bool
    {
        $activity = $this->getActivity($id);
        if (!$activity || !$activity->started_at || !$activity->ended_at) {
            return false;
        }

        // Refresh activity to get latest data
        $activity->refresh();
        $activity->load(['pauses', 'modeChanges', 'products', 'device']);

        // Use ended_at for calculation (not current time)
        $endTime = $activity->ended_at;

        // Calculate total time from started_at to ended_at
        $totalTimeInMinutes = abs($endTime->diffInMinutes($activity->started_at));
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
     * Recalculate activity price in real-time (for activities with scheduled duration)
     * Uses current time instead of ended_at
     */
    public function recalculateActivityPriceRealTime(int $id): bool
    {
        $activity = $this->getActivity($id);
        if (!$activity || !$activity->started_at) {
            return false;
        }

        // Only recalculate for activities with scheduled duration (ended_at is set)
        if (!$activity->ended_at) {
            return false;
        }

        // Refresh activity to get latest data
        $activity->refresh();
        $activity->load(['pauses', 'modeChanges', 'products', 'device']);

        // Use current time for calculation (not ended_at)
        $currentTime = now();

        // Calculate total time from started_at to current time
        $totalTimeInMinutes = abs($currentTime->diffInMinutes($activity->started_at));
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
     * Get available users for an activity
     * Returns all users with metadata:
     * - Excludes session customer (game_sessions.customer_id)
     * - Includes all users (even those in active activities, but marked as unavailable)
     * - Users in active activities are marked with 'in_active_activity' = true
     * - Users in paused activities are included and can be moved
     */
    public function getAvailableUsersForActivity(int $activityId): SupportCollection
    {
        // Get the activity and its session
        $activity = $this->getActivity($activityId);
        if (!$activity) {
            return collect([]);
        }

        $session = Session::find($activity->session_id);
        if (!$session) {
            return collect([]);
        }

        // Get all users in the system
        $allUsers = User::all();

        // Get user IDs in active activities (to mark as unavailable)
        $activeActivityUserIds = ActivityUser::whereHas('sessionActivity', function ($query) {
            $query->where('status', SessionStatus::ACTIVE->value);
        })->pluck('user_id')->unique()->toArray();

        // Get user IDs already in current activity
        $currentActivityUserIds = ActivityUser::where('session_activity_id', $activityId)
            ->pluck('user_id')
            ->unique()
            ->toArray();

        // Process users and add metadata
        // Convert to Support Collection since we're adding metadata and filtering
        $usersWithMetadata = $allUsers->map(function ($user) use ($session, $activeActivityUserIds, $currentActivityUserIds) {
            // Exclude session customer
            if ($session->customer_id == $user->id) {
                return null;
            }

            // Check if user is in active activity
            $inActiveActivity = in_array($user->id, $activeActivityUserIds);
            
            // Check if user is already in current activity
            $inCurrentActivity = in_array($user->id, $currentActivityUserIds);

            // Add metadata to user object
            $user->in_active_activity = $inActiveActivity;
            $user->in_current_activity = $inCurrentActivity;
            $user->is_available = !$inActiveActivity && !$inCurrentActivity;

            return $user;
        })->filter(); // Remove null values (excluded session customer)

        // Convert to Support Collection and reindex
        return collect($usersWithMetadata->values());
    }

    /**
     * Add a user to a session activity
     * Business rules:
     * - Removes user from any paused activities first
     * - Then adds them to the selected activity
     * - Validates user is not already in an active activity
     */
    public function addUserToActivity(int $activityId, array $data): ActivityUser
    {
        $userId = $data['user_id'];
        
        // Get the activity and its session
        $activity = $this->getActivity($activityId);
        if (!$activity) {
            throw new \Exception('Activity not found');
        }

        $session = Session::find($activity->session_id);
        if (!$session) {
            throw new \Exception('Session not found');
        }

        // Validate: Cannot add session customer
        if ($session->customer_id == $userId) {
            throw new \Exception('Cannot add session customer to activities');
        }

        // Validate: User must not be in any active activity
        $userInActiveActivity = ActivityUser::whereHas('sessionActivity', function ($query) {
            $query->where('status', SessionStatus::ACTIVE->value);
        })->where('user_id', $userId)->exists();

        if ($userInActiveActivity) {
            throw new \Exception('User is already assigned to an active activity');
        }

        // Remove user from any paused activities (they can be moved)
        $pausedActivities = ActivityUser::whereHas('sessionActivity', function ($query) {
            $query->where('status', SessionStatus::PAUSED->value);
        })->where('user_id', $userId)->get();

        foreach ($pausedActivities as $pausedActivityUser) {
            $pausedActivityUser->delete();
        }

        // Now add user to the selected activity
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

        // Delegate to specific methods for cleaner code
        if ($newStatus === SessionStatus::ENDED) {
            // Use the consolidated end logic
            if ($activity->status !== SessionStatus::ENDED) {
                $this->performEndActivityLogic($activity);
                $this->checkAndEndSessionIfNeeded($sessionId);
            }
        } elseif ($newStatus === SessionStatus::PAUSED && $oldStatus === SessionStatus::ACTIVE) {
            // Use pause method
            $this->pauseActivity($id, $sessionId);
        } elseif ($newStatus === SessionStatus::ACTIVE && $oldStatus === SessionStatus::PAUSED) {
            // Use resume method
            $this->resumeActivity($id, $sessionId);
        } else {
            // Handle other status transitions (e.g., from ENDED to ACTIVE/PAUSED)
            $updateData = ['status' => $newStatus->value];

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

            $this->sessionActivityRepository->update($id, $updateData);
        }
        
        // Return the updated activity
        return $this->getActivity($id);
    }

    /**
     * Auto-end expired activities (where ended_at <= now() and status is active)
     * This method handles activities that have a scheduled end time that has passed
     */
    public function autoEndExpiredActivities(): int
    {
        $count = 0;
        $now = now();
        
        // Find activities where:
        // - status = 'active'
        // - ended_at is not null (has scheduled end time)
        // - ended_at <= now() (scheduled time has passed)
        $expiredActivities = SessionActivity::where('status', SessionStatus::ACTIVE->value)
            ->whereNotNull('ended_at')
            ->where('ended_at', '<=', $now)
            ->get();
        
        foreach ($expiredActivities as $activity) {
            // Determine actual end time - use scheduled ended_at, but adjust if paused
            $endTime = $activity->ended_at;
            
            // Use the consolidated end logic
            $activityEndTime = $this->performEndActivityLogic($activity, $endTime);
            
            if ($activityEndTime !== null) {
                // Check if session should be ended
                $this->checkAndEndSessionIfNeeded($activity->session_id);
                $count++;
            }
        }
        
        return $count;
    }
}
