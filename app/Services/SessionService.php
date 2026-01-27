<?php

namespace App\Services;

use App\Models\Session;
use App\Models\SessionActivity;
use App\Repositories\SessionRepositoryInterface;
use App\Repositories\SessionActivityRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class SessionService
{
    private SessionRepositoryInterface $sessionRepository;
    private SessionActivityRepositoryInterface $sessionActivityRepository;
    private SessionActivityService $sessionActivityService;

    public function __construct(
        SessionRepositoryInterface $sessionRepository,
        SessionActivityRepositoryInterface $sessionActivityRepository,
        SessionActivityService $sessionActivityService
    ) {
        $this->sessionRepository = $sessionRepository;
        $this->sessionActivityRepository = $sessionActivityRepository;
        $this->sessionActivityService = $sessionActivityService;
    }

    public function getAllSessions(int $perPage = 10): LengthAwarePaginator
    {
        return $this->sessionRepository->getAllPaginated($perPage);
    }

    public function getSession(int $id): ?Session
    {
        return $this->sessionRepository->getById($id);
    }

    public function createSession(array $data): Session
    {
        // Extract device_id before creating the session
        $deviceId = $data['device_id'] ?? null;
        unset($data['device_id']);
        
        // Validate device availability if device is provided
        if ($deviceId) {
            $device = \App\Models\Device::find($deviceId);
            
            // Check if device exists
            if (!$device) {
                throw new \Exception('Device not found.');
            }
            
            // Check if device is available
            if (!$device->isAvailable()) {
                throw new \Exception('Device is not available for use. it used in another session.');
            }
            
            // Check if device is already in use in another active session activity
            $existingActivity = \App\Models\SessionActivity::where('device_id', $deviceId)
                ->whereNull('ended_at')
                ->exists();
            
            if ($existingActivity) {
                throw new \Exception('Device is already in use in another session.');
            }
        }
        
        // Extract mode for the activity - check both root level and activity_data
        $modeInput = $data['mode'] ?? $data['activity_data']['mode'] ?? 'single';
        unset($data['mode']);
        
        // Also remove activity_data if it exists (not needed after extracting mode)
        if (isset($data['activity_data'])) {
            unset($data['activity_data']);
        }
        
        // Convert to ActivityMode enum instance
        try {
            $mode = \App\Enums\ActivityMode::from($modeInput);
        } catch (\ValueError $e) {
            // If invalid, default to SINGLE
            $mode = \App\Enums\ActivityMode::SINGLE;
        }
        
        // Extract duration to calculate ended_at if provided
        $duration = $data['duration'] ?? null;
        unset($data['duration']);
        
        // Remove ended_at if it exists (should not be set directly, only calculated from duration)
        unset($data['ended_at']);
        
        // Auto-determine session type: "playing" if device selected, "chillout" if not
        if (!isset($data['type'])) {
            $data['type'] = $deviceId ? 'playing' : 'chillout';
        }
        
        // Set started_at to now() if not provided
        if (!isset($data['started_at'])) {
            $data['started_at'] = now();
        }
        
        // Calculate ended_at from duration if provided
        if ($duration !== null && $duration > 0) {
            $startedAt = is_string($data['started_at']) ? \Carbon\Carbon::parse($data['started_at']) : $data['started_at'];
            $data['ended_at'] = $startedAt->copy()->addHours($duration);
        }
        
        // Set default status if not provided
        if (!isset($data['status'])) {
            $data['status'] = 'active';
        }
        
        // Create the session
        $session = $this->sessionRepository->create($data);
        
        // Always create first activity for the session
        if ($session) {
            $activityData = [
                'session_id' => $session->id,
                'type' => $session->type?->value ?? $data['type'],
                'started_at' => $data['started_at'],
                'status' => 'active',
                'created_by' => $data['created_by'] ?? null,
            ];
            
            // Set activity ended_at from duration if provided
            if ($duration !== null && $duration > 0) {
                $activityData['ended_at'] = $data['ended_at'];
                // Set duration_hours
                $activityData['duration_hours'] = $duration;
                // Activity remains 'active' until the end time - duration just determines when it will end
                // Status stays 'active' (already set above), not 'ended'
            }
            
            if ($deviceId) {
                // Playing session: create device_use activity with device
                $activityData['activity_type'] = 'device_use';
                $activityData['device_id'] = $deviceId;
                $activityData['mode'] = $mode; // ActivityMode enum instance
            } else {
                // Chillout session: create pause activity without device
                $activityData['activity_type'] = 'pause';
                $activityData['device_id'] = null;
                $activityData['mode'] = $mode; // ActivityMode enum instance
                // Pause activities have no price
                if ($duration !== null && $duration > 0) {
                    $activityData['total_price'] = 0;
                }
            }
            
            $activity = $this->sessionActivityRepository->create($activityData);
            
            // Calculate initial price if activity has duration and is device_use
            if ($activity && $activity->isDeviceUse() && $duration !== null && $duration > 0) {
                $calculatedPrice = $this->sessionActivityService->calculateActivityPrice($activity);
                if ($calculatedPrice > 0) {
                    $activity->update(['total_price' => $calculatedPrice]);
                }
            }
            
            // NOTE: Do NOT set ended_at on initial mode change for scheduled activities!
            // The initial mode change should remain open (ended_at = null) until:
            // 1. A mode change occurs (then old period ends)
            // 2. The activity is paused/ended (then we calculate based on actual time)
            // Setting ended_at to the scheduled future time was the bug causing incorrect pricing
            
            // Automatically add the customer to the activity_user table for all activities
            if ($activity && $session->customer_id) {
                \App\Models\ActivityUser::create([
                    'session_activity_id' => $activity->id,
                    'user_id' => $session->customer_id,
                    'duration_hours' => $activity->duration_hours,
                    'cost_share' => $activity->total_price ?? 0,
                ]);
            }
        }
        
        // Reload the session with relationships to include the newly created activity
        $session->refresh();
        $session->load(['creator', 'customer', 'activities.device', 'activities.activityUsers.user']);
        
        return $session;
    }

    public function updateSession(int $id, array $data): bool
    {
        $session = $this->getSession($id);
        if (!$session) {
            return false;
        }
        
        // Check if status is being changed
        if (isset($data['status']) && $data['status'] !== $session->status->value) {
            $newStatus = $data['status'];
            
            // Update activities to match the new session status
            if ($newStatus === 'ended') {
                // If session is ended, update ALL activities to ended
                $session->activities()->update(['status' => 'ended']);
            } else {
                // For paused or active, only update activities that are not ended
                $session->activities()
                    ->whereNull('ended_at')
                    ->update(['status' => $newStatus]);
            }
        }
        
        return $this->sessionRepository->update($id, $data);
    }

    public function deleteSession(int $id): bool
    {
        return $this->sessionRepository->delete($id);
    }

    public function getSessionsByCustomer(int $customerId, int $perPage = 10): LengthAwarePaginator
    {
        return $this->sessionRepository->getByCustomerId($customerId, $perPage);
    }

    public function getSessionsByStatus(string $status, int $perPage = 10): LengthAwarePaginator
    {
        return $this->sessionRepository->getByStatus($status, $perPage);
    }

    public function getSessionsByStartDate(string $date, ?string $endDate = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->sessionRepository->getByStartDate($date, $endDate, $perPage);
    }

    /**
     * Get all non-ended activities for a session (active, paused, or any status except ended)
     */
    public function getActiveActivities(int $sessionId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->sessionActivityRepository->getBySessionId($sessionId)
            ->filter(function ($activity) {
                // Get all activities that are not ended (status != ENDED)
                return $activity->status !== \App\Enums\SessionStatus::ENDED;
            })
            ->values();
    }

    /**
     * End a session and all its active activities
     */
    public function endSession(int $id, array $data = []): array
    {
        $session = $this->getSession($id);
        if (!$session) {
            return ['success' => false, 'message' => 'Session not found'];
        }

        // Step 1: Validation - If session status is already ended, do nothing
        if ($session->status === \App\Enums\SessionStatus::ENDED) {
            return ['success' => true, 'message' => 'Session is already ended'];
        }

        // Step 2: Get all non-ended activities and end them
        $nonEndedActivities = $this->getActiveActivities($id);
        
        // Automatically end all non-ended activities when ending a session
        // No confirmation required - ending a session should end all activities

        $endTime = now();
        $activityEndTimes = [];
        $allRunning = true;
        $allPaused = true;
        $maxPausedAt = null;

        // End each activity using the proper service method to ensure all logic is executed
        foreach ($nonEndedActivities as $activity) {
            // Skip if already ended (shouldn't happen, but safety check)
            if ($activity->status === \App\Enums\SessionStatus::ENDED) {
                continue;
            }

            // Determine end time based on activity state (before ending)
            $activityEndTime = null;
            
            if ($activity->isRunning()) {
                // Activity is running: end_at = now
                $activityEndTime = $endTime;
                $allPaused = false;
            } elseif ($activity->hasPausedStatus()) {
                // Activity is paused: end_at = last_paused_at
                $lastPausedAt = $activity->getLastPausedAt();
                if ($lastPausedAt) {
                    $activityEndTime = $lastPausedAt;
                    // Track max paused_at for session end time calculation
                    if ($maxPausedAt === null || $lastPausedAt->gt($maxPausedAt)) {
                        $maxPausedAt = $lastPausedAt;
                    }
                } else {
                    // Fallback: if no pause record found, use now
                    $activityEndTime = $endTime;
                    $allPaused = false;
                }
                $allRunning = false;
            } else {
                // Fallback: use now for any other status
                $activityEndTime = $endTime;
                $allPaused = false;
            }

            // Use the proper endActivity method to ensure all logic is executed
            // This handles: completing pauses, mode changes, price recalculation, device freeing, etc.
            // Skip session check to prevent recursive calls when ending session
            $ended = $this->sessionActivityService->endActivity($activity->id, $id, [
                'skip_session_check' => true
            ]);

            if ($ended) {
                // Refresh activity to get updated data
                $activity->refresh();
                
                // Store the end time for session end time calculation
                if ($activity->ended_at) {
                    $activityEndTimes[] = $activity->ended_at;
                } else {
                    // Fallback if ended_at is not set
                    $activityEndTimes[] = $activityEndTime;
                }
            } else {
                // If ending failed, still track the intended end time
                $activityEndTimes[] = $activityEndTime;
            }
        }

        // Step 3: Verify all activities are ended
        $remainingNonEndedActivities = $this->getActiveActivities($id);
        if ($remainingNonEndedActivities->count() > 0) {
            return [
                'success' => false,
                'message' => 'Failed to end all activities. Session cannot be ended while it contains active or paused activities.',
                'remaining_activities_count' => $remainingNonEndedActivities->count(),
            ];
        }

        // Step 4: Calculate session end_at
        $sessionEndTime = null;
        
        if (count($activityEndTimes) === 0) {
            // No activities to end, use now
            $sessionEndTime = $endTime;
        } elseif ($allRunning) {
            // Case A: All activities were running → session.end_at = now
            $sessionEndTime = $endTime;
        } elseif ($allPaused && $maxPausedAt) {
            // Case B: All activities were paused → session.end_at = MAX(last_paused_at)
            $sessionEndTime = $maxPausedAt;
        } else {
            // Case C: Mixed states → session.end_at = now
            $sessionEndTime = $endTime;
        }

        // Step 5: Finalize session
        $updateData = [
            'status' => 'ended',
            'ended_at' => $sessionEndTime,
        ];
        
        // Apply discount if provided
        if (isset($data['discount'])) {
            $updateData['discount'] = $data['discount'];
        }
        
        $this->sessionRepository->update($id, $updateData);

        // Recalculate session total price (this will include discount)
        $session->refresh();
        $session->calculateTotalPrice();

        return [
            'success' => true,
            'message' => 'Session ended successfully. All activities have been ended.',
            'ended_activities_count' => $nonEndedActivities->count(),
        ];
    }

    public function pauseSession(int $id, ?int $pausedBy = null): bool
    {
        $session = $this->getSession($id);
        if (!$session) {
            return false;
        }
        
        $pauseTime = now();
        
        // Get all non-ended activities
        $activities = $session->activities()
            ->whereNull('ended_at')
            ->get();
        
        foreach ($activities as $activity) {
            // Only create pause record if activity is currently active (not already paused)
            if ($activity->status === \App\Enums\SessionStatus::ACTIVE) {
                // Create pause record
                \App\Models\ActivityPause::create([
                    'session_activity_id' => $activity->id,
                    'paused_at' => $pauseTime,
                    'paused_by' => $pausedBy,
                ]);
            }
            
            // Update activity status to paused
            $activity->update(['status' => 'paused']);
            
            // Recalculate price for each activity based on active time up to pause point
            $this->sessionActivityService->recalculateActivityPrice($activity->id, $pauseTime);
        }
        
        // Update session status
        return $this->sessionRepository->update($id, ['status' => 'paused']);
    }

    public function resumeSession(int $id, ?int $resumedBy = null): bool
    {
        $session = $this->getSession($id);
        if (!$session) {
            return false;
        }
        
        $resumeTime = now();
        
        // Get all non-ended activities
        $activities = $session->activities()
            ->whereNull('ended_at')
            ->get();
        
        foreach ($activities as $activity) {
            // Only complete pause record if activity is currently paused
            if ($activity->status === \App\Enums\SessionStatus::PAUSED) {
                // Find active pause record (not resumed yet)
                $activePause = $activity->activePause();
                
                if ($activePause) {
                    // Complete the pause record
                    $activePause->update([
                        'resumed_at' => $resumeTime,
                        'resumed_by' => $resumedBy,
                    ]);
                    
                    // Calculate pause duration
                    $activePause->calculateDuration();
                }
            }
            
            // Update activity status to active
            $activity->update(['status' => 'active']);
        }
        
        // Update session status
        return $this->sessionRepository->update($id, ['status' => 'active']);
    }

    /**
     * Get all users in a session through activities
     */
    public function getSessionUsers(int $id)
    {
        $session = $this->getSession($id);
        if (!$session) {
            return null;
        }

        return $session->getSessionUsers();
    }
}
