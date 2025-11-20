<?php

namespace App\Services;

use App\Models\SessionActivity;
use App\Models\ActivityUser;
use App\Models\Device;
use App\Enums\SessionStatus;
use App\Enums\DeviceStatus;
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

    public function endActivity(int $id, array $data): bool
    {
        $data['ended_at'] = now();
        $updated = $this->sessionActivityRepository->update($id, $data);
        
        // Model events will handle session total recalculation automatically
        return $updated;
    }

    public function calculateDuration(int $id): bool
    {
        $activity = $this->getActivity($id);
        if (!$activity || !$activity->started_at || !$activity->ended_at) {
            return false;
        }

        $duration = $activity->ended_at->diffInHours($activity->started_at);
        $totalPrice = $duration * ($activity->price_per_hour ?? 0);

        // Model events will automatically recalculate session total when total_price is updated
        return $this->sessionActivityRepository->update($id, [
            'duration_hours' => $duration,
            'total_price' => $totalPrice,
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
    public function updateActivityStatus(int $id, array $data): ?SessionActivity
    {
        $activity = $this->getActivity($id);
        
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
            $updateData['ended_at'] = now();
            
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

        // If changing to PAUSED from ACTIVE, we might want to track pause time
        // If changing to ACTIVE from PAUSED, we might want to resume tracking

        // Model events will automatically recalculate session total when total_price is updated
        $this->sessionActivityRepository->update($id, $updateData);
        
        // Return the updated activity
        return $this->getActivity($id);
    }
}
