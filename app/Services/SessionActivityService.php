<?php

namespace App\Services;

use App\Models\SessionActivity;
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
        return $this->sessionActivityRepository->update($id, $data);
    }

    public function calculateDuration(int $id): bool
    {
        $activity = $this->getActivity($id);
        if (!$activity || !$activity->started_at || !$activity->ended_at) {
            return false;
        }

        $duration = $activity->ended_at->diffInHours($activity->started_at);
        $totalPrice = $duration * ($activity->price_per_hour ?? 0);

        return $this->sessionActivityRepository->update($id, [
            'duration_hours' => $duration,
            'total_price' => $totalPrice,
        ]);
    }
}
