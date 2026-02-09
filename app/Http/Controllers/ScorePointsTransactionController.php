<?php

namespace App\Http\Controllers;

use App\Models\ScorePointsTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScorePointsTransactionController extends Controller
{
    /**
     * List all point transactions (admin)
     * Optionally filter by user_id
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 50);
        $userId = $request->integer('user_id', 0);

        $query = ScorePointsTransaction::query()->with('user:id,name,username,email');

        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($transactions);
    }
}
