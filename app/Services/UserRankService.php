<?php

namespace App\Services;

use App\Models\ScorePointsTransaction;
use App\Models\User;
use App\Models\UserPointBalance;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class UserRankService
{
    public const PERIOD_TODAY = 'today';
    public const PERIOD_MONTH = 'month';
    public const PERIOD_ALL_TIME = 'all-time';

    /**
     * Get rank for a specific user in a period.
     *
     * @return array{rank: int|null, points: float, total_users: int, in_leaderboard: bool, message: string|null}
     */
    public function getRank(int $userId, string $period, ?int $month = null, ?int $year = null): array
    {
        $noActivity = [
            'rank' => null,
            'points' => 0.0,
            'total_users' => 0,
            'in_leaderboard' => false,
            'message' => 'No activity in this period',
        ];

        $user = User::find($userId);
        if (!$user) {
            return $noActivity;
        }

        $ranked = $this->getRankedUsers($period, $month, $year);
        if ($ranked->isEmpty()) {
            return $noActivity;
        }

        $found = $ranked->firstWhere('user_id', $userId);
        if (!$found) {
            return $noActivity;
        }

        return [
            'rank' => $found['rank'],
            'points' => (float) $found['period_points'],
            'total_users' => $ranked->count(),
            'in_leaderboard' => true,
            'message' => null,
        ];
    }

    /**
     * Get leaderboard for a period with pagination.
     *
     * @return array{data: Collection, meta: array}
     */
    public function getLeaderboard(string $period, int $limit = 10, int $page = 1, ?int $month = null, ?int $year = null): array
    {
        $limit = max(1, min(100, $limit));
        $page = max(1, $page);

        $ranked = $this->getRankedUsers($period, $month, $year);
        $total = $ranked->count();

        $paginated = $ranked
            ->forPage($page, $limit)
            ->values();

        // Load users with role for response
        $userIds = $paginated->pluck('user_id')->unique()->filter()->values()->all();
        $users = User::with('role')
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        $items = $paginated->map(function ($row) use ($users) {
            $user = $users->get($row['user_id']);
            return array_merge($row, [
                'user' => $user,
            ]);
        });

        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'per_page' => $limit,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $limit),
            ],
        ];
    }

    /**
     * Get all ranked users for a period (ordered by points desc, with rank).
     * Excludes soft-deleted users.
     */
    public function getRankedUsers(string $period, ?int $month = null, ?int $year = null): Collection
    {
        if ($period === self::PERIOD_ALL_TIME) {
            return $this->getAllTimeRanked();
        }

        [$start, $end] = $this->getPeriodBounds($period, $month, $year);
        return $this->getPeriodRanked($start, $end);
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function getPeriodBounds(string $period, ?int $month, ?int $year): array
    {
        $tz = config('app.timezone', 'UTC');

        if ($period === self::PERIOD_TODAY) {
            $start = Carbon::today($tz);
            $end = $start->copy()->addDay();
            return [$start, $end];
        }

        if ($period === self::PERIOD_MONTH) {
            $y = $year ?? now($tz)->year;
            $m = $month ?? now($tz)->month;
            $start = Carbon::createFromDate($y, $m, 1, $tz)->startOfDay();
            $end = $start->copy()->addMonth();
            return [$start, $end];
        }

        // Fallback to today
        $start = Carbon::today($tz);
        $end = $start->copy()->addDay();
        return [$start, $end];
    }

    private function getPeriodRanked(Carbon $start, Carbon $end): Collection
    {
        $rows = ScorePointsTransaction::query()
            ->selectRaw('user_id, SUM(points) as period_points')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->whereHas('user') // excludes soft-deleted users
            ->groupBy('user_id')
            ->orderByDesc('period_points')
            ->get();

        return $this->assignRanks($rows, 'period_points');
    }

    private function getAllTimeRanked(): Collection
    {
        $rows = UserPointBalance::query()
            ->selectRaw('user_id, total_points as period_points')
            ->whereHas('user') // excludes soft-deleted users
            ->orderByDesc('total_points')
            ->get();

        return $this->assignRanks($rows, 'period_points');
    }

    /**
     * Assign ranks with ties (same points = same rank).
     */
    private function assignRanks(Collection $rows, string $pointsKey): Collection
    {
        $rank = 1;
        $prevPoints = null;
        $skip = 0;

        return $rows->map(function ($row, $index) use (&$rank, &$prevPoints, &$skip, $pointsKey) {
            $points = (float) $row->{$pointsKey};
            if ($prevPoints !== null && $points < $prevPoints) {
                $rank += $skip + 1;
                $skip = 0;
            } elseif ($prevPoints !== null && $points === $prevPoints) {
                $skip++;
            }
            $prevPoints = $points;

            return [
                'user_id' => $row->user_id,
                'period_points' => $points,
                'rank' => $rank,
            ];
        });
    }
}
