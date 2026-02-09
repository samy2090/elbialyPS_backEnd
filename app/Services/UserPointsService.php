<?php

namespace App\Services;

use App\Enums\PointTransactionType;
use App\Models\ActivityProduct;
use App\Models\ActivityUser;
use App\Models\ScorePointsSetting;
use App\Models\ScorePointsTransaction;
use App\Models\SessionActivity;
use App\Models\UserPointBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserPointsService
{
    public function grantPlayTimePoints(SessionActivity $activity): void
    {
        try {
            $config = ScorePointsSetting::getConfig();
            if (!$config || !$config->isPointsSystemActive()) {
                return;
            }

            // Only device_use (playing) activities earn play-time points; chillout = products only
            if (!$activity->isDeviceUse()) {
                return;
            }

            $durationHours = (float) ($activity->duration_hours ?? 0);
            if ($durationHours <= 0) {
                return;
            }

            $pointsToGrant = (int) ceil($durationHours * (float) $config->points_per_hour);
            if ($pointsToGrant <= 0) {
                return;
            }

            $activityUsers = ActivityUser::where('session_activity_id', $activity->id)->get();
            if ($activityUsers->isEmpty()) {
                return;
            }

            DB::transaction(function () use ($activity, $activityUsers, $pointsToGrant) {
                foreach ($activityUsers as $activityUser) {
                    $this->createTransaction(
                        userId: $activityUser->user_id,
                        points: $pointsToGrant,
                        type: PointTransactionType::PLAY_TIME,
                        source: $activity,
                        description: sprintf('%s hours play time', number_format($activity->duration_hours, 2)),
                        metadata: [
                            'duration_hours' => (float) $activity->duration_hours,
                            'session_activity_id' => $activity->id,
                        ]
                    );
                }
            });
        } catch (\Throwable $e) {
            Log::error('UserPointsService::grantPlayTimePoints failed', [
                'activity_id' => $activity->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function grantProductPoints(ActivityProduct $activityProduct): void
    {
        try {
            $config = ScorePointsSetting::getConfig();
            if (!$config || !$config->isPointsSystemActive()) {
                return;
            }

            $userId = $activityProduct->ordered_by_user_id;
            if (!$userId) {
                return;
            }

            $totalPrice = (float) $activityProduct->total_price;
            $threshold = (float) $config->points_money_threshold;
            $pointsPerThreshold = (float) $config->points_per_threshold;

            if ($threshold <= 0) {
                return;
            }

            $pointsToGrant = (int) ceil(($totalPrice / $threshold) * $pointsPerThreshold);
            if ($pointsToGrant <= 0) {
                return;
            }

            DB::transaction(function () use ($activityProduct, $userId, $pointsToGrant, $totalPrice) {
                $this->createTransaction(
                    userId: $userId,
                    points: $pointsToGrant,
                    type: PointTransactionType::PRODUCT_PURCHASE,
                    source: $activityProduct,
                    description: sprintf('Product purchase: %s EGP', number_format($totalPrice, 2)),
                    metadata: [
                        'total_price' => $totalPrice,
                        'activity_product_id' => $activityProduct->id,
                    ]
                );
            });
        } catch (\Throwable $e) {
            Log::error('UserPointsService::grantProductPoints failed', [
                'activity_product_id' => $activityProduct->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function reverseProductPoints(ActivityProduct $activityProduct): void
    {
        try {
            $userId = $activityProduct->ordered_by_user_id;
            if (!$userId) {
                return;
            }

            $config = ScorePointsSetting::getConfig();
            $totalPrice = (float) $activityProduct->total_price;
            $threshold = (float) ($config->points_money_threshold ?? 50);
            $pointsPerThreshold = (float) ($config->points_per_threshold ?? 5);

            if ($threshold <= 0) {
                return;
            }

            $pointsToReverse = (int) ceil(($totalPrice / $threshold) * $pointsPerThreshold);
            if ($pointsToReverse <= 0) {
                return;
            }

            $balance = UserPointBalance::getOrCreateForUser($userId);
            $currentBalance = (float) $balance->total_points;

            // Cap reversal so balance never goes negative
            $actualReversal = min($pointsToReverse, (int) $currentBalance);

            if ($actualReversal <= 0) {
                return;
            }

            DB::transaction(function () use ($activityProduct, $userId, $actualReversal, $totalPrice) {
                $this->createTransaction(
                    userId: $userId,
                    points: -$actualReversal,
                    type: PointTransactionType::PRODUCT_REFUND,
                    source: $activityProduct,
                    description: sprintf('Product refund: %s EGP', number_format($totalPrice, 2)),
                    metadata: [
                        'total_price' => $totalPrice,
                        'activity_product_id' => $activityProduct->id,
                    ]
                );
            });
        } catch (\Throwable $e) {
            Log::error('UserPointsService::reverseProductPoints failed', [
                'activity_product_id' => $activityProduct->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Admin adjustment: add or subtract points for a user
     */
    public function adjustPoints(int $userId, float $points, ?string $description = null): ScorePointsTransaction
    {
        return $this->createTransaction(
            userId: $userId,
            points: $points,
            type: PointTransactionType::ADJUSTMENT,
            source: null,
            description: $description ?? 'Admin adjustment',
            metadata: ['adjusted_by' => auth()->id()]
        );
    }

    /**
     * Grant spin wheel points to a user.
     */
    public function grantSpinWheelPoints(
        int $userId,
        float $points,
        object $source,
        ?string $description = null,
        ?array $metadata = null
    ): ScorePointsTransaction {
        return $this->createTransaction(
            userId: $userId,
            points: $points,
            type: PointTransactionType::SPIN_WHEEL,
            source: $source,
            description: $description ?? 'Spin wheel reward',
            metadata: $metadata
        );
    }

    protected function createTransaction(
        int $userId,
        float $points,
        PointTransactionType $type,
        ?object $source,
        ?string $description = null,
        ?array $metadata = null,
    ): ScorePointsTransaction {
        $transaction = ScorePointsTransaction::create([
            'user_id' => $userId,
            'points' => $points,
            'type' => $type,
            'source_type' => $source ? get_class($source) : null,
            'source_id' => $source?->id,
            'description' => $description,
            'metadata' => $metadata,
        ]);

        $balance = UserPointBalance::getOrCreateForUser($userId);
        $newTotal = (float) $balance->total_points + $points;
        $balance->update(['total_points' => max(0, $newTotal)]);

        return $transaction;
    }
}
