<?php

namespace App\Http\Controllers;

use App\Services\SpinWheelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SpinWheelController extends Controller
{
    public function __construct(
        protected SpinWheelService $spinWheelService
    ) {}

    /**
     * Get spin wheel status, options, and user's current batch.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $status = $this->spinWheelService->getStatus($user->id);
        return response()->json($status);
    }

    /**
     * Spin the wheel. Server decides outcome and returns result.
     */
    public function spin(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $result = $this->spinWheelService->spin($user->id);
            return response()->json([
                'message' => 'Spin successful',
                'data' => [
                    'reward' => $result['reward'],
                    'option' => [
                        'id' => $result['option']->id,
                        'label' => $result['option']->label,
                    ],
                    'spins_remaining' => $result['spins_remaining'],
                    'can_choose' => $result['can_choose'],
                    'batch' => [
                        'id' => $result['batch']->id,
                        'spins_used' => $result['batch']->spins_used,
                        'current_result' => $result['batch']->current_result_reward_data,
                        'period_start' => $result['batch']->period_start?->toIso8601String(),
                        'period_end' => $result['batch']->period_end?->toIso8601String(),
                    ],
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * User chooses the current spin result. Reward is granted; period ends.
     */
    public function choose(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        try {
            $result = $this->spinWheelService->choose($user->id);
            return response()->json([
                'message' => 'Reward claimed successfully',
                'data' => [
                    'reward' => $result['reward'],
                    'reward_type' => $result['reward_type'],
                    'status' => $result['status'],
                    'batch' => [
                        'id' => $result['batch']->id,
                        'claimed_at' => $result['batch']->claimed_at?->toIso8601String(),
                    ],
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get user's spin history.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $limit = min((int) $request->get('limit', 20), 100);
        $history = $this->spinWheelService->getHistory($user->id, $limit);

        return response()->json([
            'data' => $history->map(fn ($h) => [
                'id' => $h->id,
                'option' => [
                    'id' => $h->option->id,
                    'label' => $h->option->label,
                    'reward' => $h->reward_value ?? $h->option->getRewardValueForDisplay(),
                ],
                'spin_number' => $h->spin_number,
                'spun_at' => $h->spun_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Get user's claimed rewards.
     */
    public function claims(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $limit = min((int) $request->get('limit', 20), 100);
        $claims = $this->spinWheelService->getClaims($user->id, $limit);

        return response()->json([
            'data' => $claims->map(fn ($c) => [
                'id' => $c->id,
                'option' => [
                    'id' => $c->option->id,
                    'label' => $c->option->label,
                    'reward' => $c->reward_value ?? $c->option->getRewardValueForDisplay(),
                ],
                'reward_type' => $c->reward_type,
                'status' => $c->status,
                'fulfilled_at' => $c->fulfilled_at?->toIso8601String(),
                'created_at' => $c->created_at->toIso8601String(),
            ]),
        ]);
    }
}
