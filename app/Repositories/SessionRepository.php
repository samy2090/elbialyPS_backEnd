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

    public function getByStartDate(string $date, ?string $endDate = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = Session::with(['creator', 'customer', 'activities.device', 'activities.activityUsers.user']);

        if ($endDate) {
            // Date range: from start date to end date (inclusive)
            // Start: beginning of start date (00:00:00)
            // End: end of end date (23:59:59)
            $query->whereBetween('started_at', [
                $date . ' 00:00:00',
                $endDate . ' 23:59:59'
            ]);
        } else {
            // Single date: only sessions that started on this specific day
            $query->whereDate('started_at', $date);
        }

        return $query->latest()->paginate($perPage);
    }
}
