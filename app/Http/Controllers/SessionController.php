<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateSessionRequest;
use App\Http\Requests\UpdateSessionRequest;
use App\Services\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SessionController extends Controller
{
    private SessionService $sessionService;

    public function __construct(SessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * Display a listing of sessions.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 10);
        $sessions = $this->sessionService->getAllSessions($perPage);
        return response()->json($sessions);
    }

    /**
     * Display the specified session.
     */
    public function show(int $id): JsonResponse
    {
        $session = $this->sessionService->getSession($id);
        
        if (!$session) {
            return response()->json(['message' => 'Session not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($session);
    }

    /**
     * Store a newly created session.
     */
    public function store(CreateSessionRequest $request): JsonResponse
    {
        $session = $this->sessionService->createSession($request->validated());
        return response()->json($session, Response::HTTP_CREATED);
    }

    /**
     * Update the specified session.
     */
    public function update(UpdateSessionRequest $request, int $id): JsonResponse
    {
        $success = $this->sessionService->updateSession($id, $request->validated());
        
        if (!$success) {
            return response()->json(['message' => 'Session not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Session updated successfully']);
    }

    /**
     * Remove the specified session.
     */
    public function destroy(int $id): JsonResponse
    {
        $success = $this->sessionService->deleteSession($id);
        
        if (!$success) {
            return response()->json(['message' => 'Session not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Session deleted successfully']);
    }

    /**
     * Get sessions by customer.
     */
    public function getByCustomer(Request $request, int $customerId): JsonResponse
    {
        $perPage = $request->get('per_page', 10);
        $sessions = $this->sessionService->getSessionsByCustomer($customerId, $perPage);
        return response()->json($sessions);
    }

    /**
     * Get sessions by status.
     */
    public function getByStatus(Request $request, string $status): JsonResponse
    {
        $perPage = $request->get('per_page', 10);
        $sessions = $this->sessionService->getSessionsByStatus($status, $perPage);
        return response()->json($sessions);
    }

    /**
     * End a session.
     */
    public function end(int $id, Request $request): JsonResponse
    {
        $data = $request->all();
        $result = $this->sessionService->endSession($id, $data);
        
        if (!$result['success']) {
            // If there are active activities, return them for confirmation
            if ($result['has_active_activities'] ?? false) {
                return response()->json([
                    'message' => $result['message'],
                    'has_active_activities' => true,
                    'active_activities' => $result['active_activities'],
                ], Response::HTTP_CONFLICT);
            }
            
            return response()->json(['message' => $result['message'] ?? 'Session not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => $result['message'],
            'ended_activities_count' => $result['ended_activities_count'] ?? 0,
        ]);
    }

    /**
     * Pause a session.
     */
    public function pause(int $id): JsonResponse
    {
        $success = $this->sessionService->pauseSession($id);
        
        if (!$success) {
            return response()->json(['message' => 'Session not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Session paused successfully']);
    }

    /**
     * Resume a session.
     */
    public function resume(int $id): JsonResponse
    {
        $success = $this->sessionService->resumeSession($id);
        
        if (!$success) {
            return response()->json(['message' => 'Session not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Session resumed successfully']);
    }

    /**
     * Get all users in a session (from all activities).
     */
    public function getSessionUsers(int $id): JsonResponse
    {
        $session = $this->sessionService->getSession($id);
        
        if (!$session) {
            return response()->json(['message' => 'Session not found'], Response::HTTP_NOT_FOUND);
        }

        $users = $session->getSessionUsers();
        return response()->json($users);
    }
}
