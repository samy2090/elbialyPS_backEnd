<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\AuthServiceInterface;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(protected AuthServiceInterface $auth)
    {
    }

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $result = $this->auth->register($data);

        return response()->json($result, 201);
    }

    public function login(LoginRequest $request)
    {
        $result = $this->auth->login($request->validated());

        return response()->json($result);
    }

    public function logout(Request $request)
    {
        $this->auth->logout($request);

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}