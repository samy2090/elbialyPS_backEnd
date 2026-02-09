<?php

namespace App\Http\Controllers;

use App\Models\SpinWheelClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class SpinWheelClaimController extends Controller
{
    /**
     * List all spin wheel claims (admin).
     */
    public function index(Request $request): JsonResponse
    {
        $query = SpinWheelClaim::with(['user', 'option', 'batch', 'fulfilledByUser'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }
        if ($request->filled('reward_type')) {
            $query->where('reward_type', $request->get('reward_type'));
        }

        $perPage = min((int) $request->get('per_page', 15), 100);
        $claims = $query->paginate($perPage);

        return response()->json([
            'data' => collect($claims->items())->map(fn ($c) => [
                'id' => $c->id,
                'user' => $c->user ? ['id' => $c->user->id, 'name' => $c->user->name, 'email' => $c->user->email] : null,
                'option' => $c->option ? ['id' => $c->option->id, 'label' => $c->option->label, 'reward_type' => $c->option->reward_type] : null,
                'reward_type' => $c->reward_type,
                'reward_value' => $c->reward_value,
                'status' => $c->status,
                'fulfilled_at' => $c->fulfilled_at?->toIso8601String(),
                'fulfilled_by' => $c->fulfilledByUser ? ['id' => $c->fulfilledByUser->id, 'name' => $c->fulfilledByUser->name] : null,
                'created_at' => $c->created_at?->toIso8601String(),
            ])->values()->all(),
            'meta' => [
                'current_page' => $claims->currentPage(),
                'per_page' => $claims->perPage(),
                'total' => $claims->total(),
                'last_page' => $claims->lastPage(),
            ],
        ]);
    }

    /**
     * Show a single claim.
     */
    public function show(SpinWheelClaim $spinWheelClaim): JsonResponse
    {
        $spinWheelClaim->load(['user', 'option', 'batch', 'fulfilledByUser']);

        return response()->json([
            'data' => [
                'id' => $spinWheelClaim->id,
                'user' => [
                    'id' => $spinWheelClaim->user->id,
                    'name' => $spinWheelClaim->user->name,
                    'email' => $spinWheelClaim->user->email,
                ],
                'option' => [
                    'id' => $spinWheelClaim->option->id,
                    'label' => $spinWheelClaim->option->label,
                    'reward_type' => $spinWheelClaim->option->reward_type,
                    'reward_display' => $spinWheelClaim->option->getRewardValueForDisplay(),
                ],
                'reward_type' => $spinWheelClaim->reward_type,
                'reward_value' => $spinWheelClaim->reward_value,
                'status' => $spinWheelClaim->status,
                'fulfilled_by' => $spinWheelClaim->fulfilledByUser ? [
                    'id' => $spinWheelClaim->fulfilledByUser->id,
                    'name' => $spinWheelClaim->fulfilledByUser->name,
                ] : null,
                'fulfilled_at' => $spinWheelClaim->fulfilled_at?->toIso8601String(),
                'fulfillment_notes' => $spinWheelClaim->fulfillment_notes,
                'created_at' => $spinWheelClaim->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Mark a non-points claim as fulfilled (admin).
     */
    public function fulfill(Request $request, SpinWheelClaim $spinWheelClaim): JsonResponse
    {
        if ($spinWheelClaim->status !== SpinWheelClaim::STATUS_PENDING) {
            return response()->json([
                'message' => 'Only pending claims can be marked as fulfilled.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validator = Validator::make($request->all(), [
            'fulfillment_notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $spinWheelClaim->update([
            'status' => SpinWheelClaim::STATUS_FULFILLED,
            'fulfilled_by' => $request->user()->id,
            'fulfilled_at' => now(),
            'fulfillment_notes' => $validator->validated()['fulfillment_notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Claim marked as fulfilled',
            'data' => [
                'id' => $spinWheelClaim->id,
                'status' => $spinWheelClaim->status,
                'fulfilled_at' => $spinWheelClaim->fulfilled_at->toIso8601String(),
            ],
        ]);
    }
}
