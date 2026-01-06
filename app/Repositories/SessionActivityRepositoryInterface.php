<?php

namespace App\Repositories;

use App\Models\SessionActivity;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SessionActivityRepositoryInterface
{
    public function getBySessionId(int $sessionId): Collection;
    public function getById(int $id): ?SessionActivity;
    public function getByIdAndSessionId(int $id, int $sessionId): ?SessionActivity;
    public function create(array $data): SessionActivity;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function getByActivityType(string $type, int $perPage = 10): LengthAwarePaginator;
}
