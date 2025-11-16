<?php

namespace App\Services;

use App\Models\Session;
use App\Repositories\SessionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class SessionService
{
    private SessionRepositoryInterface $sessionRepository;

    public function __construct(SessionRepositoryInterface $sessionRepository)
    {
        $this->sessionRepository = $sessionRepository;
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
        return $this->sessionRepository->create($data);
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
}
