<?php

namespace App\Services;

use Illuminate\Http\Request;

interface AuthServiceInterface
{
    public function register(array $data): array;

    public function login(array $credentials): array;

    public function logout(Request $request): void;
}
