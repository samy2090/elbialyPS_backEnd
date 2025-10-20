<?php

namespace App\Repositories;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findByUsername(string $username): ?User;

    public function findByPhone(string $phone): ?User;

    public function create(array $data): User;
}
