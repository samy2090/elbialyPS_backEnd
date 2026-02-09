<?php

namespace App\Http\Controllers;

use App\Enums\SpinWheelPeriodType;
use App\Models\SpinWheelSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class SpinWheelSettingController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = SpinWheelSetting::getConfig();
        if (!$settings) {
            return response()->json([
                'data' => null,
                'period_types' => array_map(fn ($t) => [
                    'value' => $t->value,
                    'label' => $t->label(),
                ], SpinWheelPeriodType::cases()),
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $settings->id,
                'is_active' => $settings->is_active,
                'period_type' => $settings->period_type,
                'period_value' => $settings->period_value,
                'weekday_only' => $settings->weekday_only,
                'start_date' => $settings->start_date?->format('Y-m-d'),
                'max_spins_per_period' => $settings->max_spins_per_period,
                'updated_at' => $settings->updated_at?->toIso8601String(),
            ],
            'period_types' => array_map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ], SpinWheelPeriodType::cases()),
            'weekday_options' => [
                ['value' => 0, 'label' => 'Sunday'],
                ['value' => 1, 'label' => 'Monday'],
                ['value' => 2, 'label' => 'Tuesday'],
                ['value' => 3, 'label' => 'Wednesday'],
                ['value' => 4, 'label' => 'Thursday'],
                ['value' => 5, 'label' => 'Friday'],
                ['value' => 6, 'label' => 'Saturday'],
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_active' => 'sometimes|boolean',
            'period_type' => 'sometimes|string|in:' . implode(',', SpinWheelPeriodType::values()),
            'period_value' => 'nullable|integer|min:0|max:31',
            'weekday_only' => 'sometimes|boolean',
            'start_date' => 'nullable|date',
            'max_spins_per_period' => 'sometimes|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $settings = SpinWheelSetting::first();
        if (!$settings) {
            $settings = SpinWheelSetting::create($validator->validated());
        } else {
            $settings->update($validator->validated());
        }

        return response()->json([
            'message' => 'Spin wheel settings updated successfully',
            'data' => [
                'id' => $settings->id,
                'is_active' => $settings->is_active,
                'period_type' => $settings->period_type,
                'period_value' => $settings->period_value,
                'weekday_only' => $settings->weekday_only,
                'start_date' => $settings->start_date?->format('Y-m-d'),
                'max_spins_per_period' => $settings->max_spins_per_period,
                'updated_at' => $settings->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
