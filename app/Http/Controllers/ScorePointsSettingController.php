<?php

namespace App\Http\Controllers;

use App\Models\ScorePointsSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class ScorePointsSettingController extends Controller
{
    /**
     * Get current score points settings
     */
    public function show(): JsonResponse
    {
        $settings = ScorePointsSetting::getConfig();
        if (!$settings) {
            return response()->json([
                'message' => 'Score points settings not found. Run the seeder.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => [
                'id' => $settings->id,
                'is_active' => $settings->is_active,
                'points_per_hour' => (float) $settings->points_per_hour,
                'points_money_threshold' => (float) $settings->points_money_threshold,
                'points_per_threshold' => (float) $settings->points_per_threshold,
                'points_expiry_enabled' => $settings->points_expiry_enabled,
                'points_expiry_type' => $settings->points_expiry_type,
                'points_expiry_day_of_month' => $settings->points_expiry_day_of_month,
                'points_expiry_specific_date' => $settings->points_expiry_specific_date?->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Update score points settings
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_active' => 'sometimes|boolean',
            'points_per_hour' => 'sometimes|numeric|min:0',
            'points_money_threshold' => 'sometimes|numeric|min:0',
            'points_per_threshold' => 'sometimes|numeric|min:0',
            'points_expiry_enabled' => 'sometimes|boolean',
            'points_expiry_type' => 'nullable|string|in:monthly,specific_date',
            'points_expiry_day_of_month' => 'nullable|integer|min:1|max:31',
            'points_expiry_specific_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $settings = ScorePointsSetting::first();
        if (!$settings) {
            $settings = ScorePointsSetting::create($validator->validated());
        } else {
            $settings->update($validator->validated());
        }

        return response()->json([
            'message' => 'Score points settings updated successfully',
            'data' => [
                'id' => $settings->id,
                'is_active' => $settings->is_active,
                'points_per_hour' => (float) $settings->points_per_hour,
                'points_money_threshold' => (float) $settings->points_money_threshold,
                'points_per_threshold' => (float) $settings->points_per_threshold,
                'points_expiry_enabled' => $settings->points_expiry_enabled,
                'points_expiry_type' => $settings->points_expiry_type,
                'points_expiry_day_of_month' => $settings->points_expiry_day_of_month,
                'points_expiry_specific_date' => $settings->points_expiry_specific_date?->format('Y-m-d'),
            ],
        ]);
    }
}
