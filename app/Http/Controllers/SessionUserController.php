<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateSessionUserRequest;
use App\Http\Requests\UpdateSessionUserRequest;
use App\Services\SessionUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SessionUserController extends Controller
{
    private SessionUserService $sessionUserService;

    public function __construct(SessionUserService $sessionUserService)
    {
        $this->sessionUserService = $sessionUserService;
    }

    /**
     * Display users in a session.
     */
    public function index(int $sessionId): JsonResponse
    {
        $sessionUsers = $this->sessionUserService->getSessionUsers($sessionId);
        return response()->json($sessionUsers);
    }

    /**
     * Display a specific session user.
     */
    public function show(int $id): JsonResponse
    {
        $sessionUser = $this->sessionUserService->getSessionUser($id);
        
        if (!$sessionUser) {
            return response()->json(['message' => 'Session user not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($sessionUser);
    }

    /**
     * Add a user to a session.
     */
    public function store(CreateSessionUserRequest $request): JsonResponse
    {
        $sessionUser = $this->sessionUserService->addUserToSession($request->validated());
        return response()->json($sessionUser, Response::HTTP_CREATED);
    }

    /**
     * Update session user (e.g., set as payer).
     */
    public function update(UpdateSessionUserRequest $request, int $id): JsonResponse
    {
        $success = $this->sessionUserService->updateSessionUser($id, $request->validated());
        
        if (!$success) {
            return response()->json(['message' => 'Session user not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Session user updated successfully']);
    }

    /**
     * Remove a user from a session.
     */
    public function destroy(int $id): JsonResponse
    {
        $success = $this->sessionUserService->removeUserFromSession($id);
        
        if (!$success) {
            return response()->json(['message' => 'Session user not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'User removed from session']);
    }

    /**
     * Set a user as payer for a session.
     */
    public function setPayer(int $sessionId, int $userId): JsonResponse
    {
        $success = $this->sessionUserService->setSessionPayer($sessionId, $userId, true);
        
        if (!$success) {
            return response()->json(['message' => 'Session or user not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Payer set successfully']);
    }
}
