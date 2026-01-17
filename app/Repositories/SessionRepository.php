<?php

namespace App\Repositories;

use App\Models\Session;
use Illuminate\Pagination\LengthAwarePaginator;

class SessionRepository implements SessionRepositoryInterface
{
    public function getAllPaginated(int $perPage = 10): LengthAwarePaginator
    {
        return Session::with(['creator', 'customer', 'activities.device', 'activities.activityUsers.user'])
            ->latest()
            ->paginate($perPage);
    }

    public function getById(int $id): ?Session
    {
        return Session::with(['creator', 'customer', 'activities.device', 'activities.activityUsers.user'])
            ->find($id);
    }

    public function create(array $data): Session
    {
        return Session::create($data);
    }

    public function update(int $id, array $data): bool
    {
        return Session::where('id', $id)->update($data);
    }

    public function delete(int $id): bool
    {
        return Session::destroy($id) > 0;
    }

    public function getByCustomerId(int $customerId, int $perPage = 10): LengthAwarePaginator
    {
        return Session::where('customer_id', $customerId)
            ->with(['creator', 'customer', 'activities.device', 'activities.activityUsers.user'])
            ->latest()
            ->paginate($perPage);
    }

    public function getByStatus(string $status, int $perPage = 10): LengthAwarePaginator
    {
        return Session::where('status', $status)
            ->with(['creator', 'customer', 'activities.device', 'activities.activityUsers.user'])
            ->latest()
            ->paginate($perPage);
    }
}
