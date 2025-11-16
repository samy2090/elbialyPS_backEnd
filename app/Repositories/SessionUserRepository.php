<?php

namespace App\Repositories;

use App\Models\SessionUser;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SessionUserRepository implements SessionUserRepositoryInterface
{
    public function getBySessionId(int $sessionId): Collection
    {
        return SessionUser::where('session_id', $sessionId)
            ->with(['session', 'user'])
            ->get();
    }

    public function getById(int $id): ?SessionUser
    {
        return SessionUser::with(['session', 'user'])->find($id);
    }

    public function create(array $data): SessionUser
    {
        return SessionUser::create($data);
    }

    public function update(int $id, array $data): bool
    {
        return SessionUser::where('id', $id)->update($data);
    }

    public function delete(int $id): bool
    {
        return SessionUser::destroy($id) > 0;
    }

    public function findBySessionAndUser(int $sessionId, int $userId): ?SessionUser
    {
        return SessionUser::where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->first();
    }
}
