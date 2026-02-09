<?php

namespace App\Http\Controllers;

use App\Enums\SpinWheelRewardType;
use App\Models\SpinWheelOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class SpinWheelOptionController extends Controller
{
    public function index(): JsonResponse
    {
        $options = SpinWheelOption::with('product')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $options->map(fn ($o) => [
                'id' => $o->id,
                'label' => $o->label,
                'reward_type' => $o->reward_type,
                'value' => $o->value,
                'product_id' => $o->product_id,
                'product' => $o->product ? ['id' => $o->product->id, 'name' => $o->product->name] : null,
                'weight' => $o->weight,
                'max_claims_per_period' => $o->max_claims_per_period,
                'is_active' => $o->is_active,
                'display_order' => $o->display_order,
                'reward_display' => $o->getRewardValueForDisplay(),
                'created_at' => $o->created_at?->toIso8601String(),
            ]),
            'reward_types' => array_map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ], SpinWheelRewardType::cases()),
        ]);
    }

    public function show(SpinWheelOption $spinWheelOption): JsonResponse
    {
        $o = $spinWheelOption->load('product');
        return response()->json([
            'data' => [
                'id' => $o->id,
                'label' => $o->label,
                'reward_type' => $o->reward_type,
                'value' => $o->value,
                'product_id' => $o->product_id,
                'product' => $o->product ? ['id' => $o->product->id, 'name' => $o->product->name] : null,
                'weight' => $o->weight,
                'max_claims_per_period' => $o->max_claims_per_period,
                'is_active' => $o->is_active,
                'display_order' => $o->display_order,
                'reward_display' => $o->getRewardValueForDisplay(),
                'created_at' => $o->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'label' => 'required|string|max:255',
            'reward_type' => 'required|string|in:' . implode(',', SpinWheelRewardType::values()),
            'value' => 'nullable|numeric|min:0',
            'product_id' => 'nullable|exists:products,id',
            'weight' => 'required|integer|min:1',
            'max_claims_per_period' => 'sometimes|nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'display_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $validator->validated();
        if (($data['reward_type'] ?? '') === SpinWheelRewardType::FREE_PRODUCT->value) {
            $data['value'] = null;
        } elseif (($data['reward_type'] ?? '') === SpinWheelRewardType::POINTS->value) {
            $data['product_id'] = null;
        } else {
            $data['product_id'] = null;
        }

        $option = SpinWheelOption::create($data);

        return response()->json([
            'message' => 'Spin wheel option created successfully',
            'data' => [
                'id' => $option->id,
                'label' => $option->label,
                'reward_type' => $option->reward_type,
                'value' => $option->value,
                'product_id' => $option->product_id,
                'weight' => $option->weight,
                'max_claims_per_period' => $option->max_claims_per_period,
                'is_active' => $option->is_active,
                'display_order' => $option->display_order,
            ],
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, SpinWheelOption $spinWheelOption): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'label' => 'sometimes|string|max:255',
            'reward_type' => 'sometimes|string|in:' . implode(',', SpinWheelRewardType::values()),
            'value' => 'nullable|numeric|min:0',
            'product_id' => 'nullable|exists:products,id',
            'weight' => 'sometimes|integer|min:1',
            'max_claims_per_period' => 'sometimes|nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'display_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $validator->validated();
        if (isset($data['reward_type']) && $data['reward_type'] === SpinWheelRewardType::FREE_PRODUCT->value) {
            $data['value'] = null;
        } elseif (isset($data['reward_type']) && $data['reward_type'] === SpinWheelRewardType::POINTS->value) {
            $data['product_id'] = null;
        }

        $spinWheelOption->update($data);

        return response()->json([
            'message' => 'Spin wheel option updated successfully',
            'data' => $spinWheelOption->fresh(['product']),
        ]);
    }

    public function destroy(SpinWheelOption $spinWheelOption): JsonResponse
    {
        $spinWheelOption->delete();

        return response()->json([
            'message' => 'Spin wheel option deleted successfully',
        ], Response::HTTP_NO_CONTENT);
    }
}
