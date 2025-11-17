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
        
        // Create the session
        $session = $this->sessionRepository->create($data);
        
        // Create initial activity for the session if device_id is provided
        if ($deviceId && $session) {
            $this->sessionActivityRepository->create([
                'session_id' => $session->id,
                'activity_type' => 'device_use',
                'device_id' => $deviceId,
                'mode' => 'single',
                'started_at' => $data['started_at'] ?? now(),
                'status' => 'active',
                'created_by' => $data['created_by'] ?? null,
            ]);
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

    public function endSession(int $id, array $data): bool
    {
        $data['status'] = 'ended';
        $data['ended_at'] = now();
        return $this->sessionRepository->update($id, $data);
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
