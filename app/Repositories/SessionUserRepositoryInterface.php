<?php

namespace App\Repositories;

use App\Models\SessionUser;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SessionUserRepositoryInterface
{
    public function getBySessionId(int $sessionId): Collection;
    public function getById(int $id): ?SessionUser;
    public function create(array $data): SessionUser;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function findBySessionAndUser(int $sessionId, int $userId): ?SessionUser;
}
