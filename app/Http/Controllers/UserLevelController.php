<?php

namespace App\Http\Controllers;

use App\Models\UserLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class UserLevelController extends Controller
{
    /**
     * List all user levels
     */
    public function index(): JsonResponse
    {
        $levels = UserLevel::orderBy('min_points', 'asc')->get();

        return response()->json([
            'data' => $levels,
        ]);
    }

    /**
     * Show a single user level
     */
    public function show(UserLevel $userLevel): JsonResponse
    {
        return response()->json([
            'data' => $userLevel,
        ]);
    }

    /**
     * Create a new user level
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'min_points' => 'required|numeric|min:0',
            'perks' => 'nullable|array',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $level = UserLevel::create($validator->validated());

        return response()->json([
            'message' => 'User level created successfully',
            'data' => $level,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update a user level
     */
    public function update(Request $request, UserLevel $userLevel): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'min_points' => 'sometimes|numeric|min:0',
            'perks' => 'nullable|array',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $userLevel->update($validator->validated());

        return response()->json([
            'message' => 'User level updated successfully',
            'data' => $userLevel->fresh(),
        ]);
    }

    /**
     * Delete a user level
     */
    public function destroy(UserLevel $userLevel): JsonResponse
    {
        $userLevel->delete();

        return response()->json([
            'message' => 'User level deleted successfully',
        ], Response::HTTP_NO_CONTENT);
    }
}
