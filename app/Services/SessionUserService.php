<?php

namespace App\Services;

use App\Models\SessionUser;
use App\Repositories\SessionUserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SessionUserService
{
    private SessionUserRepositoryInterface $sessionUserRepository;

    public function __construct(SessionUserRepositoryInterface $sessionUserRepository)
    {
        $this->sessionUserRepository = $sessionUserRepository;
    }

    public function getSessionUsers(int $sessionId): Collection
    {
        return $this->sessionUserRepository->getBySessionId($sessionId);
    }

    public function getSessionUser(int $id): ?SessionUser
    {
        return $this->sessionUserRepository->getById($id);
    }

    public function addUserToSession(array $data): SessionUser
    {
        return $this->sessionUserRepository->create($data);
    }

    public function updateSessionUser(int $id, array $data): bool
    {
        return $this->sessionUserRepository->update($id, $data);
    }

    public function removeUserFromSession(int $id): bool
    {
        return $this->sessionUserRepository->delete($id);
    }

    public function findSessionUser(int $sessionId, int $userId): ?SessionUser
    {
        return $this->sessionUserRepository->findBySessionAndUser($sessionId, $userId);
    }

    public function setSessionPayer(int $sessionId, int $userId, bool $isPayer = true): bool
    {
        $sessionUser = $this->findSessionUser($sessionId, $userId);
        if (!$sessionUser) {
            return false;
        }

        // If setting this user as payer, remove payer status from others
        if ($isPayer) {
            $this->sessionUserRepository->update(null, ['is_payer' => false], ['session_id' => $sessionId]);
        }

        return $this->sessionUserRepository->update($sessionUser->id, ['is_payer' => $isPayer]);
    }
}
