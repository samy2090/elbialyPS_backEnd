<?php

namespace App\Repositories;

use App\Models\Session;
use Illuminate\Pagination\LengthAwarePaginator;

interface SessionRepositoryInterface
{
    public function getAllPaginated(int $perPage = 10): LengthAwarePaginator;
    public function getById(int $id): ?Session;
    public function create(array $data): Session;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function getByCustomerId(int $customerId, int $perPage = 10): LengthAwarePaginator;
    public function getByStatus(string $status, int $perPage = 10): LengthAwarePaginator;
}
