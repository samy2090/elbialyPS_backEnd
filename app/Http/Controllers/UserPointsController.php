<?php

namespace App\Http\Controllers;

use App\Models\UserLevel;
use App\Models\UserPointBalance;
use App\Models\ScorePointsTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserPointsController extends Controller
{
    /**
     * Get current user's points balance
     */
    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();
        $balance = UserPointBalance::getOrCreateForUser($user->id);
        $level = UserLevel::getLevelForPoints((float) $balance->total_points);

        return response()->json([
            'data' => [
                'total_points' => (float) $balance->total_points,
                'level' => $level ? [
                    'id' => $level->id,
                    'name' => $level->name,
                    'min_points' => (float) $level->min_points,
                    'perks' => $level->perks,
                ] : null,
            ],
        ]);
    }

    /**
     * Get current user's point transactions (paginated)
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min($request->integer('per_page', 15), 50);

        $transactions = ScorePointsTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($transactions);
    }

    /**
     * Get all user levels (for display in UI)
     */
    public function levels(): JsonResponse
    {
        $levels = UserLevel::orderBy('min_points', 'asc')->get();

        return response()->json([
            'data' => $levels->map(fn ($level) => [
                'id' => $level->id,
                'name' => $level->name,
                'min_points' => (float) $level->min_points,
                'perks' => $level->perks,
                'sort_order' => $level->sort_order,
            ]),
        ]);
    }
}
