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

    public function __construct(
        SessionRepositoryInterface $sessionRepository,
        SessionActivityRepositoryInterface $sessionActivityRepository
    ) {
        $this->sessionRepository = $sessionRepository;
        $this->sessionActivityRepository = $sessionActivityRepository;
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
        
        // Auto-determine session type: "playing" if device selected, "chillout" if not
        if (!isset($data['type'])) {
            $data['type'] = $deviceId ? 'playing' : 'chillout';
        }
        
        // Set started_at to now() if not provided
        if (!isset($data['started_at'])) {
            $data['started_at'] = now();
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
            
            if ($deviceId) {
                // Playing session: create device_use activity with device
                $activityData['activity_type'] = 'device_use';
                $activityData['device_id'] = $deviceId;
                $activityData['mode'] = 'single';
            } else {
                // Chillout session: create pause activity without device
                $activityData['activity_type'] = 'pause';
                $activityData['device_id'] = null;
                $activityData['mode'] = 'single';
            }
            
            $this->sessionActivityRepository->create($activityData);
        }
        
        return $session;
    }

    public function updateSession(int $id, array $data): bool
    {
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

    /**
     * Get active activities for a session
     */
    public function getActiveActivities(int $sessionId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->sessionActivityRepository->getBySessionId($sessionId)
            ->filter(function ($activity) {
                return $activity->ended_at === null;
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

        // Check for active activities
        $activeActivities = $this->getActiveActivities($id);
        
        // If there are active activities and not confirmed, return them for confirmation
        if ($activeActivities->isNotEmpty() && !($data['confirm_end_activities'] ?? false)) {
            return [
                'success' => false,
                'has_active_activities' => true,
                'active_activities' => $activeActivities->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'type' => $activity->type?->value,
                        'activity_type' => $activity->activity_type?->value,
                        'device_id' => $activity->device_id,
                        'started_at' => $activity->started_at,
                    ];
                }),
                'message' => 'There are active activities. Please confirm to end them all.',
            ];
        }

        // End all active activities first
        $endTime = now();
        $lastActivityEndTime = null;

        foreach ($activeActivities as $activity) {
            // Calculate duration if activity has started_at
            if ($activity->started_at && !$activity->ended_at) {
                // Calculate duration in hours (with decimal precision)
                $durationInSeconds = $activity->started_at->diffInSeconds($endTime);
                $duration = round($durationInSeconds / 3600, 2); // Convert to hours with 2 decimal places
                
                // Calculate total price if price_per_hour is set (for device_use activities)
                $totalPrice = 0;
                if ($activity->price_per_hour && $activity->activity_type === \App\Enums\ActivityType::DEVICE_USE) {
                    $totalPrice = round($duration * $activity->price_per_hour, 2);
                }
                
                // Update activity
                $this->sessionActivityRepository->update($activity->id, [
                    'ended_at' => $endTime,
                    'status' => 'ended',
                    'duration_hours' => $duration,
                    'total_price' => $totalPrice,
                ]);
            } else {
                // If activity doesn't have started_at, just end it
                $this->sessionActivityRepository->update($activity->id, [
                    'ended_at' => $endTime,
                    'status' => 'ended',
                ]);
            }
            
            $lastActivityEndTime = $endTime;
        }

        // Get the last activity's end time (from all activities, not just active ones)
        $lastActivity = $session->activities()
            ->whereNotNull('ended_at')
            ->orderBy('ended_at', 'desc')
            ->first();
        
        $sessionEndTime = $lastActivity ? $lastActivity->ended_at : $endTime;

        // Update session
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
            'message' => 'Session ended successfully',
            'ended_activities_count' => $activeActivities->count(),
        ];
    }

    public function pauseSession(int $id): bool
    {
        return $this->sessionRepository->update($id, ['status' => 'paused']);
    }

    public function resumeSession(int $id): bool
    {
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
