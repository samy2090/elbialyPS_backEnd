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
        
        // For pause activities, set price to 0 if duration is provided
        if (isset($data['activity_type']) && $data['activity_type'] === 'pause' && $duration !== null && $duration > 0) {
            $data['total_price'] = 0;
        }
        
        $activity = $this->sessionActivityRepository->create($data);
        
        // Calculate initial price if activity has duration and is device_use
        if ($activity && $activity->isDeviceUse() && $duration !== null && $duration > 0) {
            $calculatedPrice = $this->calculateActivityPrice($activity);
            if ($calculatedPrice > 0) {
                $activity->update(['total_price' => $calculatedPrice]);
            }
        }
        
        return $activity;
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
                
                // Recalculate price immediately after mode change
                // For scheduled activities (has ended_at), include remaining time
                // For unlimited activities, just recalculate real time used
                if ($activity->ended_at) {
                    // Scheduled activity: real time used + remaining time until ended_at × current mode
                    $this->recalculateActivityPriceForResume($id);
                } else {
                    // Unlimited activity: just real time used
                    $this->recalculateActivityPrice($id);
                }
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

        // Create pause record if activity is not ended and doesn't already have an active pause
        // Note: scheduled activities have ended_at (future time), but are still active until that time
        // So we check status, not ended_at
        $pauseTime = now();
        if ($activity->status !== SessionStatus::ENDED && !$activity->activePause()) {
            ActivityPause::create([
                'session_activity_id' => $activity->id,
                'paused_at' => $pauseTime,
                'paused_by' => auth()->id(),
            ]);
        }

        // Update activity status to paused
        $updated = $this->sessionActivityRepository->update($id, ['status' => SessionStatus::PAUSED->value]);
        
        // Recalculate price based on active time up to pause point
        // This works for both scheduled and unlimited activities
        if ($updated) {
            $this->recalculateActivityPrice($id, $pauseTime);
            
            // Explicitly recalculate session total to ensure it's updated
            if ($activity->session_id) {
                $session = Session::find($activity->session_id);
                if ($session) {
                    $session->calculateTotalPrice();
                }
            }
        }
        
        return $updated;
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
        $updated = $this->sessionActivityRepository->update($id, ['status' => SessionStatus::ACTIVE->value]);
        
        // Recalculate price on resume ONLY for scheduled activities (has ended_at)
        // For unlimited activities (no ended_at), no recalculation needed
        if ($updated && $activity->ended_at) {
            // For scheduled activities: calculate real active time + remaining time until ended_at
            // This ensures we account for the full scheduled duration
            $this->recalculateActivityPriceForResume($id);
        }
        
        return $updated;
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

        // Recalculate price with the determined end time (actual end time, not scheduled)
        // Force refresh to ensure we have the latest data including the updated ended_at
        $activity->refresh();
        $activity->load(['pauses', 'modeChanges', 'products', 'device']);
        
        // Always recalculate if activity has started_at
        // Use the actual end time (activityEndTime) not the scheduled one
        // This is critical when ending early - we want to calculate based on actual time used
        if ($activity->started_at) {
            $this->recalculateActivityPrice($activity->id, $activityEndTime);
            
            // Explicitly recalculate session total to ensure it's updated
            // (Model events should handle this, but ensure it happens)
            if ($activity->session_id) {
                $session = Session::find($activity->session_id);
                if ($session) {
                    $session->calculateTotalPrice();
                }
            }
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
     * Recalculate activity price based on actual active time, mode periods, and products
     * Works for both scheduled and unlimited activities
     * 
     * @param int $id Activity ID
     * @param \Carbon\Carbon|null $endTime Optional end time (for pause calculations or ended activities)
     * @return bool Success status
     */
    public function recalculateActivityPrice(int $id, ?\Carbon\Carbon $endTime = null): bool
    {
        // Get fresh activity data directly from repository to avoid caching issues
        $activity = $this->sessionActivityRepository->getById($id);
        if (!$activity || !$activity->started_at) {
            return false;
        }

        // Refresh activity to get latest data including all relationships
        $activity->refresh();
        $activity->load(['pauses', 'modeChanges', 'products', 'device']);

        // Determine calculation end time:
        // 1. If $endTime provided, use it (for pause calculations or ended activities)
        //    This is critical when ending early - we want actual time, not scheduled time
        // 2. Else if activity has ended_at, use it (for scheduled activities)
        // 3. Else use now() (for unlimited active activities)
        if ($endTime === null) {
            $endTime = $activity->ended_at ?? now();
        }
        
        // Ensure endTime is not in the future (shouldn't happen, but safety check)
        if ($endTime > now() && $activity->status !== SessionStatus::ENDED) {
            $endTime = now();
        }

        // Calculate total time from started_at to end time
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
                    // For active mode change periods, use endTime instead of ended_at
                    $periodEnd = $modeChange->ended_at ?? $endTime;
                    
                    // Calculate raw period duration in minutes
                    $periodDurationMinutes = abs($periodStart->diffInMinutes($periodEnd));
                    
                    // Calculate pause overlap with this period
                    // Get all pauses and check overlap in PHP for better accuracy
                    $pauseMinutesInPeriod = 0;
                    foreach ($activity->pauses as $pause) {
                        $pauseStart = $pause->paused_at;
                        // For active pauses, use endTime instead of ended_at
                        $pauseEnd = $pause->resumed_at ?? $endTime;
                        
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

        // Update activity with calculated price and duration
        // Use repository update to ensure it's saved properly
        $updated = $this->sessionActivityRepository->update($id, [
            'duration_hours' => round($realDurationHours, 2),
            'total_price' => round($totalPrice, 2),
        ]);
        
        // Ensure the update was successful
        if ($updated) {
            // Refresh the activity to get the updated values
            $activity->refresh();
        }
        
        return $updated;
    }

    /**
     * Recalculate activity price when resuming a scheduled activity
     * For scheduled activities: real active time used + remaining time until ended_at × current mode + products
     * 
     * @param int $id Activity ID
     * @return bool Success status
     */
    private function recalculateActivityPriceForResume(int $id): bool
    {
        $activity = $this->sessionActivityRepository->getById($id);
        if (!$activity || !$activity->started_at || !$activity->ended_at) {
            return false;
        }

        // Refresh activity to get latest data
        $activity->refresh();
        $activity->load(['pauses', 'modeChanges', 'products', 'device']);

        $now = now();
        $currentMode = $activity->mode;

        // Part 1: Calculate real active time used (up to now, excluding pauses) with mode periods
        $realActiveTimeUsed = $this->calculateRealActiveTimeUsed($activity, $now);
        
        // Part 2: Calculate remaining time until ended_at
        $remainingTimeHours = 0;
        if ($activity->ended_at > $now) {
            $remainingTimeMinutes = abs($now->diffInMinutes($activity->ended_at));
            $remainingTimeHours = $remainingTimeMinutes / 60;
        }

        // Calculate device usage price
        $deviceUsagePrice = 0;
        
        if ($activity->isDeviceUse() && $activity->device_id) {
            $device = $activity->device;
            $singlePricePerHour = (float) $device->price_per_hour;
            $multiPricePerHour = $device->multi_price 
                ? (float) $device->multi_price
                : $singlePricePerHour;

            // Calculate price for real active time used (with mode periods)
            $realActivePrice = $this->calculateDevicePriceForTimePeriod($activity, $activity->started_at, $now);
            
            // Calculate price for remaining time (using current mode)
            $remainingPrice = 0;
            if ($remainingTimeHours > 0) {
                $remainingPricePerHour = $currentMode === ActivityMode::MULTI 
                    ? $multiPricePerHour 
                    : $singlePricePerHour;
                $remainingPrice = $remainingTimeHours * $remainingPricePerHour;
            }
            
            $deviceUsagePrice = $realActivePrice + $remainingPrice;
        }

        // Calculate products total
        $productsTotal = (float) ($activity->products()->sum('total_price') ?? 0);

        // Total = real active time price + remaining time price + products
        $totalPrice = $deviceUsagePrice + $productsTotal;

        // Calculate total real duration (active time used + remaining time)
        $totalRealDurationHours = $realActiveTimeUsed['totalHours'] + $remainingTimeHours;

        // Update activity
        return $this->sessionActivityRepository->update($id, [
            'duration_hours' => round($totalRealDurationHours, 2),
            'total_price' => round($totalPrice, 2),
        ]);
    }

    /**
     * Calculate real active time used (excluding pauses) with mode periods
     * 
     * @param SessionActivity $activity
     * @param \Carbon\Carbon $endTime
     * @return array ['totalHours' => float, 'singleHours' => float, 'multiHours' => float]
     */
    private function calculateRealActiveTimeUsed(SessionActivity $activity, \Carbon\Carbon $endTime): array
    {
        $totalTimeInMinutes = abs($endTime->diffInMinutes($activity->started_at));
        $totalTimeInHours = $totalTimeInMinutes / 60;
        
        $totalPauseDurationHours = $activity->getTotalPauseDurationHours();
        $realDurationHours = max(0, $totalTimeInHours - $totalPauseDurationHours);

        $singleHours = 0;
        $multiHours = 0;

        if ($activity->isDeviceUse() && $activity->device_id) {
            $modeChanges = $activity->modeChanges()->orderBy('changed_at', 'asc')->get();

            if ($modeChanges->isEmpty()) {
                // No mode changes - use current mode for entire duration
                if ($activity->mode === ActivityMode::SINGLE) {
                    $singleHours = $realDurationHours;
                } else {
                    $multiHours = $realDurationHours;
                }
            } else {
                // Calculate time for each mode period
                foreach ($modeChanges as $modeChange) {
                    $periodStart = $modeChange->changed_at;
                    $periodEnd = $modeChange->ended_at ?? $endTime;
                    
                    // Don't calculate beyond endTime
                    if ($periodEnd > $endTime) {
                        $periodEnd = $endTime;
                    }
                    
                    $periodDurationMinutes = abs($periodStart->diffInMinutes($periodEnd));
                    
                    // Calculate pause overlap
                    $pauseMinutesInPeriod = 0;
                    foreach ($activity->pauses as $pause) {
                        $pauseStart = $pause->paused_at;
                        $pauseEnd = $pause->resumed_at ?? $endTime;
                        
                        if ($pauseStart < $periodEnd && $pauseEnd > $periodStart) {
                            $overlapStart = $pauseStart > $periodStart ? $pauseStart : $periodStart;
                            $overlapEnd = $pauseEnd < $periodEnd ? $pauseEnd : $periodEnd;
                            
                            if ($overlapStart < $overlapEnd) {
                                $pauseMinutesInPeriod += abs($overlapStart->diffInMinutes($overlapEnd));
                            }
                        }
                    }
                    
                    $activeMinutes = max(0, $periodDurationMinutes - $pauseMinutesInPeriod);
                    $activeHours = $activeMinutes / 60;
                    
                    $modeEnum = ActivityMode::from($modeChange->to_mode);
                    if ($modeEnum === ActivityMode::SINGLE) {
                        $singleHours += $activeHours;
                    } else {
                        $multiHours += $activeHours;
                    }
                }
            }
        }

        return [
            'totalHours' => $realDurationHours,
            'singleHours' => $singleHours,
            'multiHours' => $multiHours,
        ];
    }

    /**
     * Calculate device price for a time period with mode periods
     * 
     * @param SessionActivity $activity
     * @param \Carbon\Carbon $startTime
     * @param \Carbon\Carbon $endTime
     * @return float
     */
    private function calculateDevicePriceForTimePeriod(SessionActivity $activity, \Carbon\Carbon $startTime, \Carbon\Carbon $endTime): float
    {
        if (!$activity->isDeviceUse() || !$activity->device_id) {
            return 0;
        }

        $device = $activity->device;
        $singlePricePerHour = (float) $device->price_per_hour;
        $multiPricePerHour = $device->multi_price 
            ? (float) $device->multi_price
            : $singlePricePerHour;

        $timeUsed = $this->calculateRealActiveTimeUsed($activity, $endTime);
        
        return ($timeUsed['singleHours'] * $singlePricePerHour) + ($timeUsed['multiHours'] * $multiPricePerHour);
    }

    /**
     * Calculate initial price for an activity when creating with duration
     * 
     * @param SessionActivity $activity Activity instance
     * @return float Calculated price
     */
    public function calculateActivityPrice(SessionActivity $activity): float
    {
        // Unlimited activities have no initial price
        if (!$activity->ended_at) {
            return 0;
        }

        // Non-device activities have no price
        if (!$activity->isDeviceUse() || !$activity->device_id) {
            return 0;
        }

        $device = $activity->device;
        if (!$device) {
            return 0;
        }

        // Get duration from duration_hours or calculate from dates
        $duration = $activity->duration_hours ?? 
            ($activity->ended_at->diffInHours($activity->started_at));

        // Get price based on mode
        $pricePerHour = $activity->mode === ActivityMode::MULTI
            ? ($device->multi_price ?? $device->price_per_hour)
            : $device->price_per_hour;

        return round($duration * (float) $pricePerHour, 2);
    }

    /**
     * Calculate duration and total price for an activity
     * Uses ended_at timestamp (not current time) - for activities that have ended
     * This method is kept for backward compatibility and now calls recalculateActivityPrice()
     */
    public function calculateDuration(int $id): bool
    {
        $activity = $this->getActivity($id);
        if (!$activity || !$activity->started_at || !$activity->ended_at) {
            return false;
        }

        // Use the unified recalculation method with ended_at
        return $this->recalculateActivityPrice($id, $activity->ended_at);
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
