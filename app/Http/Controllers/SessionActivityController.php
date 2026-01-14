<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateSessionActivityRequest;
use App\Http\Requests\UpdateSessionActivityRequest;
use App\Http\Requests\UpdateActivityStatusRequest;
use App\Http\Requests\CreateActivityProductRequest;
use App\Services\SessionActivityService;
use App\Models\ActivityProduct;
use App\Models\Product;
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
     * Get available users for an activity.
     */
    public function getAvailableUsers(int $sessionId, int $activityId): JsonResponse
    {
        // Validate that activity belongs to the session
        $activity = $this->sessionActivityService->getActivity($activityId);
        if (!$activity || $activity->session_id != $sessionId) {
            return response()->json(['message' => 'Activity not found in this session'], Response::HTTP_NOT_FOUND);
        }

        $users = $this->sessionActivityService->getAvailableUsersForActivity($activityId);
        return response()->json($users);
    }

    /**
     * Add a user to an activity.
     */
    public function addUser(int $sessionId, int $activityId, \Illuminate\Http\Request $request): JsonResponse
    {
        // Validate that activity belongs to the session
        $activity = $this->sessionActivityService->getActivity($activityId);
        if (!$activity || $activity->session_id != $sessionId) {
            return response()->json(['message' => 'Activity not found in this session'], Response::HTTP_NOT_FOUND);
        }

        // Rule: Cannot add users to ended activities
        if ($activity->status === \App\Enums\SessionStatus::ENDED) {
            return response()->json([
                'message' => 'Cannot add users to ended activities'
            ], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'duration_hours' => 'nullable|numeric|min:0',
            'cost_share' => 'nullable|numeric|min:0',
        ]);

        try {
            $activityUser = $this->sessionActivityService->addUserToActivity($activityId, $validated);
            
            // Load the user relationship for response
            $activityUser->load('user');
            
            return response()->json([
                'message' => 'User added to activity successfully',
                'data' => $activityUser
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
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

    // ==================== Activity Products Methods ====================

    /**
     * Get all products for an activity
     */
    public function getActivityProducts(int $sessionId, int $activityId): JsonResponse
    {
        // Validate that activity belongs to the session
        $activity = $this->sessionActivityService->getActivity($activityId);
        if (!$activity || $activity->session_id != $sessionId) {
            return response()->json(['message' => 'Activity not found in this session'], Response::HTTP_NOT_FOUND);
        }

        $products = ActivityProduct::where('session_activity_id', $activityId)
            ->with(['product', 'orderedByUser'])
            ->get();

        return response()->json(['data' => $products]);
    }

    /**
     * Add a product to an activity
     */
    public function addProductToActivity(int $sessionId, int $activityId, CreateActivityProductRequest $request): JsonResponse
    {
        // Validate that activity belongs to the session
        $activity = $this->sessionActivityService->getActivity($activityId);
        if (!$activity || $activity->session_id != $sessionId) {
            return response()->json(['message' => 'Activity not found in this session'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();

        // Get the product to fetch current price
        $product = Product::find($validated['product_id']);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        // Calculate prices
        $price = (float) $product->price;
        $totalPrice = $price * $validated['quantity'];

        // Create activity product
        $activityProduct = ActivityProduct::create([
            'session_activity_id' => $activityId,
            'product_id' => $validated['product_id'],
            'quantity' => $validated['quantity'],
            'price' => $price,
            'total_price' => $totalPrice,
            'ordered_by_user_id' => $validated['ordered_by_user_id'],
        ]);

        // Load relationships for response
        $activityProduct->load(['product', 'orderedByUser']);

        return response()->json([
            'message' => 'Product added to activity successfully',
            'data' => $activityProduct
        ], Response::HTTP_CREATED);
    }

    /**
     * Update a product order in an activity
     */
    public function updateActivityProduct(int $sessionId, int $activityId, int $productOrderId, CreateActivityProductRequest $request): JsonResponse
    {
        // Validate that activity belongs to the session
        $activity = $this->sessionActivityService->getActivity($activityId);
        if (!$activity || $activity->session_id != $sessionId) {
            return response()->json(['message' => 'Activity not found in this session'], Response::HTTP_NOT_FOUND);
        }

        // Validate that product order belongs to the activity
        $activityProduct = ActivityProduct::where('id', $productOrderId)
            ->where('session_activity_id', $activityId)
            ->first();

        if (!$activityProduct) {
            return response()->json(['message' => 'Product order not found in this activity'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();

        // Get the product to fetch current price (in case product_id changed)
        $product = Product::find($validated['product_id']);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        // Calculate prices
        $price = (float) $product->price;
        $totalPrice = $price * $validated['quantity'];

        // Update activity product
        $activityProduct->update([
            'product_id' => $validated['product_id'],
            'quantity' => $validated['quantity'],
            'price' => $price,
            'total_price' => $totalPrice,
            'ordered_by_user_id' => $validated['ordered_by_user_id'],
        ]);

        // Load relationships for response
        $activityProduct->load(['product', 'orderedByUser']);

        return response()->json([
            'message' => 'Product order updated successfully',
            'data' => $activityProduct
        ]);
    }

    /**
     * Delete a product order from an activity
     */
    public function deleteActivityProduct(int $sessionId, int $activityId, int $productOrderId): JsonResponse
    {
        // Validate that activity belongs to the session
        $activity = $this->sessionActivityService->getActivity($activityId);
        if (!$activity || $activity->session_id != $sessionId) {
            return response()->json(['message' => 'Activity not found in this session'], Response::HTTP_NOT_FOUND);
        }

        // Validate that product order belongs to the activity
        $activityProduct = ActivityProduct::where('id', $productOrderId)
            ->where('session_activity_id', $activityId)
            ->first();

        if (!$activityProduct) {
            return response()->json(['message' => 'Product order not found in this activity'], Response::HTTP_NOT_FOUND);
        }

        $activityProduct->delete();

        return response()->json(['message' => 'Product order deleted successfully']);
    }

    /**
     * Get products ordered by a specific user in an activity
     */
    public function getActivityProductsByUser(int $sessionId, int $activityId, int $userId): JsonResponse
    {
        // Validate that activity belongs to the session
        $activity = $this->sessionActivityService->getActivity($activityId);
        if (!$activity || $activity->session_id != $sessionId) {
            return response()->json(['message' => 'Activity not found in this session'], Response::HTTP_NOT_FOUND);
        }

        $products = ActivityProduct::where('session_activity_id', $activityId)
            ->where('ordered_by_user_id', $userId)
            ->with(['product', 'orderedByUser'])
            ->get();

        return response()->json(['data' => $products]);
    }
}