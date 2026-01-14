<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateSessionActivityRequest;
use App\Http\Requests\UpdateSessionActivityRequest;
use App\Http\Requests\UpdateActivityStatusRequest;
use App\Services\SessionActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SessionActivityController extends Controller
{
    private SessionActivityService $sessionActivityService;

    public function __construct(SessionActivityService $sessionActivityService)
    {
        $this->sessionActivityService = $sessionActivityService;
    }

    /**
     * Display activities in a session.
     */
    public function index(int $sessionId): JsonResponse
    {
        $activities = $this->sessionActivityService->getSessionActivities($sessionId);
        return response()->json($activities);
    }

    /**
     * Display a specific activity.
     */
    public function show(int $sessionId, int $id): JsonResponse
    {
        $activity = $this->sessionActivityService->getActivity($id);
        
        if (!$activity || $activity->session_id != $sessionId) {
            return response()->json(['message' => 'Activity not found in this session'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($activity);
    }

    /**
     * Store a newly created activity.
     */
    public function store(int $sessionId, CreateSessionActivityRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['session_id'] = $sessionId;
        $activity = $this->sessionActivityService->createActivity($data);
        return response()->json($activity, Response::HTTP_CREATED);
    }

    /**
     * Update the specified activity.
     */
    public function update(UpdateSessionActivityRequest $request, int $sessionId, int $id): JsonResponse
    {
        // Validate that activity belongs to the session
        $activity = $this->sessionActivityService->getActivity($id);
        if (!$activity || $activity->session_id != $sessionId) {
            return response()->json(['message' => 'Activity not found in this session'], Response::HTTP_NOT_FOUND);
        }

        $success = $this->sessionActivityService->updateActivity($id, $request->validated());
        
        if (!$success) {
            return response()->json(['message' => 'Activity not found'], Response::HTTP_NOT_FOUND);
        }

        // Fetch and return the updated activity
        $updatedActivity = $this->sessionActivityService->getActivity($id);
        
        return response()->json([
            'message' => 'Activity updated successfully',
            'data' => $updatedActivity
        ]);
    }

    /**
     * Remove the specified activity.
     */
    public function destroy(int $sessionId, int $id): JsonResponse
    {
        // Validate that activity belongs to the session
        $activity = $this->sessionActivityService->getActivity($id);
        if (!$activity || $activity->session_id != $sessionId) {
            return response()->json(['message' => 'Activity not found in this session'], Response::HTTP_NOT_FOUND);
        }

        $success = $this->sessionActivityService->deleteActivity($id);
        
        if (!$success) {
            return response()->json(['message' => 'Activity not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Activity deleted successfully']);
    }

    /**
     * Get activities by type.
     */
    public function getByType(string $type): JsonResponse
    {
        $activities = $this->sessionActivityService->getActivitiesByType($type);
        return response()->json($activities);
    }

    /**
     * Update activity status.
     */
    public function updateStatus(int $sessionId, int $id, UpdateActivityStatusRequest $request): JsonResponse
    {
        $activity = $this->sessionActivityService->updateActivityStatus($id, $sessionId, $request->validated());
        
        if (!$activity) {
            return response()->json(['message' => 'Activity not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => 'Activity status updated successfully',
            'activity' => $activity
        ]);
    }

    /**
     * End an activity.
     */
    public function end(int $sessionId, int $id, UpdateSessionActivityRequest $request): JsonResponse
    {
        $success = $this->sessionActivityService->endActivity($id, $sessionId, $request->validated());
        
        if (!$success) {
            return response()->json(['message' => 'Activity not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Activity ended successfully']);
    }

    /**
     * Calculate duration and total price for an activity.
     */
    public function calculateDuration(int $id): JsonResponse
    {
        $success = $this->sessionActivityService->calculateDuration($id);
        
        if (!$success) {
            return response()->json(['message' => 'Cannot calculate duration - activity or dates not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Duration calculated successfully']);
    }

    /**
     * Add a user to an activity.
     */
    public function addUser(int $sessionId, int $activityId, \Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'duration_hours' => 'nullable|numeric|min:0',
            'cost_share' => 'nullable|numeric|min:0',
        ]);

        $activityUser = $this->sessionActivityService->addUserToActivity($activityId, $validated);
        return response()->json($activityUser, Response::HTTP_CREATED);
    }

    /**
     * Remove a user from an activity.
     */
    public function removeUser(int $sessionId, int $activityId, int $userId): JsonResponse
    {
        $success = $this->sessionActivityService->removeUserFromActivity($activityId, $userId);
        
        if (!$success) {
            return response()->json(['message' => 'Activity or user not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'User removed from activity']);
    }
}