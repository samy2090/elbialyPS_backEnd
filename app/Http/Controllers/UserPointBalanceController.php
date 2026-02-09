<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserPointBalance;
use App\Models\UserLevel;
use App\Services\UserPointsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class UserPointBalanceController extends Controller
{
    public function __construct(
        private UserPointsService $userPointsService
    ) {}

    /**
     * List all users with their point balances (admin)
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 15), 50);
        $search = $request->string('search')->trim();

        $query = User::query()
            ->with('pointBalance', 'role')
            ->leftJoin('user_point_balances', 'users.id', '=', 'user_point_balances.user_id')
            ->select('users.*')
            ->orderByRaw('COALESCE(user_point_balances.total_points, 0) DESC');

        if ($search->isNotEmpty()) {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('users.username', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($perPage);

        $data = $users->getCollection()->map(function ($user) {
            $balance = $user->pointBalance;
            $totalPoints = $balance ? (float) $balance->total_points : 0;
            $level = UserLevel::getLevelForPoints($totalPoints);
            return [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'total_points' => $totalPoints,
                'level' => $level ? [
                    'id' => $level->id,
                    'name' => $level->name,
                    'min_points' => (float) $level->min_points,
                ] : null,
            ];
        });

        $users->setCollection($data);

        return response()->json($users);
    }

    /**
     * Admin adjustment: add or subtract points for a user
     */
    public function adjust(Request $request, int $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'points' => 'required|numeric',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $points = (float) $validator->validated()['points'];
        $description = $validator->validated()['description'] ?? null;

        if ($points == 0) {
            return response()->json([
                'message' => 'Points cannot be zero',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $transaction = $this->userPointsService->adjustPoints($userId, $points, $description);
        $balance = UserPointBalance::getOrCreateForUser($userId);

        return response()->json([
            'message' => 'Points adjusted successfully',
            'data' => [
                'transaction' => $transaction,
                'new_balance' => (float) $balance->total_points,
            ],
        ], Response::HTTP_CREATED);
    }
}
