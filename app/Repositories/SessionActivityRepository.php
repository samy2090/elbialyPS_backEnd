<?php

namespace App\Repositories;

use App\Models\SessionActivity;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SessionActivityRepository implements SessionActivityRepositoryInterface
{
    public function getBySessionId(int $sessionId): Collection
    {
        return SessionActivity::where('session_id', $sessionId)
            ->with(['session', 'device', 'creator', 'activityUsers', 'products'])
            ->get();
    }

    public function getById(int $id): ?SessionActivity
    {
        return SessionActivity::with(['session', 'device', 'creator', 'activityUsers', 'products'])
            ->find($id);
    }

    public function create(array $data): SessionActivity
    {
        return SessionActivity::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $activity = SessionActivity::find($id);
        if (!$activity) {
            return false;
        }
        
        // Use model instance update to trigger model events
        return $activity->update($data);
    }

    public function delete(int $id): bool
    {
        return SessionActivity::destroy($id) > 0;
    }

    public function getByActivityType(string $type, int $perPage = 10): LengthAwarePaginator
    {
        return SessionActivity::where('activity_type', $type)
            ->with(['session', 'device', 'creator', 'activityUsers', 'products'])
            ->paginate($perPage);
    }
}
