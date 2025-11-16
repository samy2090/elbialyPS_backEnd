<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateSessionActivityRequest;
use App\Http\Requests\UpdateSessionActivityRequest;
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
    public function show(int $id): JsonResponse
    {
        $activity = $this->sessionActivityService->getActivity($id);
        
        if (!$activity) {
            return response()->json(['message' => 'Activity not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($activity);
    }

    /**
     * Store a newly created activity.
     */
    public function store(CreateSessionActivityRequest $request): JsonResponse
    {
        $activity = $this->sessionActivityService->createActivity($request->validated());
        return response()->json($activity, Response::HTTP_CREATED);
    }

    /**
     * Update the specified activity.
     */
    public function update(UpdateSessionActivityRequest $request, int $id): JsonResponse
    {
        $success = $this->sessionActivityService->updateActivity($id, $request->validated());
        
        if (!$success) {
            return response()->json(['message' => 'Activity not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Activity updated successfully']);
    }

    /**
     * Remove the specified activity.
     */
    public function destroy(int $id): JsonResponse
    {
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
     * End an activity.
     */
    public function end(int $id, UpdateSessionActivityRequest $request): JsonResponse
    {
        $success = $this->sessionActivityService->endActivity($id, $request->validated());
        
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
}
