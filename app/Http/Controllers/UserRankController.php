<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserRankAdminResource;
use App\Http\Resources\UserRankPublicResource;
use App\Models\User;
use App\Services\UserRankService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserRankController extends Controller
{
    public function __construct(
        private UserRankService $rankService
    ) {}

    /**
     * Get current user's rank (authenticated only).
     */
    public function myRank(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'string', 'in:today,month,all-time'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'year' => ['sometimes', 'integer', 'min:2020', 'max:2100'],
        ]);

        $period = $validated['period'];
        $month = $validated['month'] ?? null;
        $year = $validated['year'] ?? null;

        if ($period === UserRankService::PERIOD_MONTH && ($month === null || $year === null)) {
            $now = now();
            $month = $month ?? $now->month;
            $year = $year ?? $now->year;
        }

        $result = $this->rankService->getRank($request->user()->id, $period, $month, $year);

        $response = [
            'data' => array_merge($result, [
                'period' => $period,
                'month' => $period === UserRankService::PERIOD_MONTH ? (int) $month : null,
                'year' => $period === UserRankService::PERIOD_MONTH ? (int) $year : null,
            ]),
        ];

        return response()->json($response);
    }

    /**
     * Get a specific user's rank (public; full details for admin/staff).
     */
    public function userRank(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'string', 'in:today,month,all-time'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'year' => ['sometimes', 'integer', 'min:2020', 'max:2100'],
        ]);

        $period = $validated['period'];
        $month = $validated['month'] ?? null;
        $year = $validated['year'] ?? null;

        if ($period === UserRankService::PERIOD_MONTH && ($month === null || $year === null)) {
            $now = now();
            $month = $month ?? $now->month;
            $year = $year ?? $now->year;
        }

        $user = User::with('role')->find($userId);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found',
            ], 404);
        }

        $result = $this->rankService->getRank($userId, $period, $month, $year);

        $isAdminOrStaff = $request->user() && $request->user()->isAdminOrStaff();

        $resourceClass = $isAdminOrStaff ? UserRankAdminResource::class : UserRankPublicResource::class;
        $userData = new $resourceClass([
            'user' => $user,
            'rank' => $result['rank'],
            'period_points' => $result['points'],
        ]);

        return response()->json([
            'data' => array_merge($userData->toArray($request), [
                'total_users' => $result['total_users'],
                'in_leaderboard' => $result['in_leaderboard'],
                'message' => $result['message'],
                'period' => $period,
                'month' => $period === UserRankService::PERIOD_MONTH ? (int) $month : null,
                'year' => $period === UserRankService::PERIOD_MONTH ? (int) $year : null,
            ]),
        ]);
    }

    /**
     * Get leaderboard (public, no auth).
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'string', 'in:today,month,all-time'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'year' => ['sometimes', 'integer', 'min:2020', 'max:2100'],
        ]);

        $period = $validated['period'];
        $limit = $validated['limit'] ?? 10;
        $page = $validated['page'] ?? 1;
        $month = $validated['month'] ?? null;
        $year = $validated['year'] ?? null;

        if ($period === UserRankService::PERIOD_MONTH && ($month === null || $year === null)) {
            $now = now();
            $month = $month ?? $now->month;
            $year = $year ?? $now->year;
        }

        $result = $this->rankService->getLeaderboard($period, $limit, $page, $month, $year);

        $isAdminOrStaff = $request->user() && $request->user()->isAdminOrStaff();
        $resourceClass = $isAdminOrStaff ? UserRankAdminResource::class : UserRankPublicResource::class;

        $data = collect($result['data'])->map(fn ($item) => (new $resourceClass($item))->toArray($request));

        return response()->json([
            'data' => $data,
            'meta' => array_merge($result['meta'], [
                'period' => $period,
                'month' => $period === UserRankService::PERIOD_MONTH ? (int) $month : null,
                'year' => $period === UserRankService::PERIOD_MONTH ? (int) $year : null,
            ]),
        ]);
    }
}
