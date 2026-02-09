<?php

namespace App\Services;

use App\Enums\SpinWheelPeriodType;
use App\Enums\SpinWheelRewardType;
use App\Models\SpinWheelClaim;
use App\Models\SpinWheelOption;
use App\Models\SpinWheelSetting;
use App\Models\SpinWheelSpinHistory;
use App\Models\SpinWheelUserBatch;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SpinWheelService
{
    public function __construct(
        protected UserPointsService $pointsService,
    ) {}

    public function getConfig(): ?SpinWheelSetting
    {
        return SpinWheelSetting::getConfig();
    }

    /**
     * Get current period boundaries for a user.
     * Returns [period_start, period_end] in app timezone.
     */
    public function getCurrentPeriodBounds(int $userId): ?array
    {
        $config = $this->getConfig();
        if (!$config || !$config->isActive()) {
            return null;
        }

        $tz = config('app.timezone');
        $now = Carbon::now($tz);
        $type = SpinWheelPeriodType::tryFrom($config->period_type);
        $value = (int) ($config->period_value ?? 1);

        return match ($type) {
            SpinWheelPeriodType::EVERY_N_DAYS => $this->periodEveryNDays($config, $now, $value),
            SpinWheelPeriodType::EVERY_WEEKDAY => $this->periodEveryWeekday($config, $now, $value),
            SpinWheelPeriodType::EVERY_MONTH => $this->periodEveryMonth($config, $now),
            SpinWheelPeriodType::COOLDOWN_DAYS => $this->periodCooldown($userId, $now, $value),
            default => null,
        };
    }

    protected function periodEveryNDays(SpinWheelSetting $config, Carbon $now, int $value): ?array
    {
        $tz = config('app.timezone');
        $startDate = $config->start_date ? Carbon::parse($config->start_date, $tz)->startOfDay() : $now->copy()->startOfDay();
        if ($value < 1) {
            $value = 1;
        }
        $daysSinceStart = $startDate->diffInDays($now, false);
        if ($daysSinceStart < 0) {
            return null; // before start
        }
        $periodIndex = (int) floor($daysSinceStart / $value);
        $periodStart = $startDate->copy()->addDays($periodIndex * $value)->startOfDay();
        $periodEnd = $periodStart->copy()->addDays($value)->endOfDay();
        return [$periodStart, $periodEnd];
    }

    protected function periodEveryWeekday(SpinWheelSetting $config, Carbon $now, int $weekday): ?array
    {
        $tz = config('app.timezone');
        $startDate = $config->start_date ? Carbon::parse($config->start_date, $tz) : Carbon::today($tz);
        // weekday: 0=Sun, 1=Mon, ..., 6=Sat
        $targetDay = $weekday % 7;
        $weekdayOnly = (bool) $config->weekday_only;

        if ($weekdayOnly) {
            // Wheel open only on that weekday
            $currentDay = (int) $now->format('w');
            if ($currentDay !== $targetDay) {
                return null;
            }
            $periodStart = $now->copy()->startOfDay();
            $periodEnd = $now->copy()->endOfDay();
            return [$periodStart, $periodEnd];
        }

        // From that weekday to the next same weekday
        $firstOccurrence = $startDate->copy();
        while ((int) $firstOccurrence->format('w') !== $targetDay) {
            $firstOccurrence->addDay();
        }
        if ($firstOccurrence->isAfter($now)) {
            return null;
        }
        $daysSince = $firstOccurrence->diffInDays($now, false);
        $periodsSince = (int) floor($daysSince / 7);
        $periodStart = $firstOccurrence->copy()->addWeeks($periodsSince)->startOfDay();
        $periodEnd = $periodStart->copy()->addDays(7)->subSecond();
        return [$periodStart, $periodEnd];
    }

    protected function periodEveryMonth(SpinWheelSetting $config, Carbon $now): ?array
    {
        $periodStart = $now->copy()->startOfMonth();
        $periodEnd = $now->copy()->endOfMonth();
        return [$periodStart, $periodEnd];
    }

    /**
     * Cooldown: next period is allowed only after last claim + cooldown_days.
     * No fallback "today + days" when user never claimed — batch reuse is handled in getOrCreateUserBatch.
     */
    protected function periodCooldown(int $userId, Carbon $now, int $days): ?array
    {
        $lastClaimed = SpinWheelUserBatch::where('user_id', $userId)
            ->where('status', SpinWheelUserBatch::STATUS_CLAIMED)
            ->orderByDesc('claimed_at')
            ->first();

        if (!$lastClaimed || !$lastClaimed->claimed_at) {
            return null;
        }

        $claimedAt = Carbon::parse($lastClaimed->claimed_at, config('app.timezone'));
        $cooldownEnd = $claimedAt->copy()->addDays($days)->subSecond();

        if ($now->isAfter($cooldownEnd)) {
            $periodStart = $cooldownEnd->copy()->addSecond();
            $periodEnd = $periodStart->copy()->addDays($days)->subSecond();
            return [$periodStart, $periodEnd];
        }

        return null;
    }

    /**
     * Check if wheel is available for the user right now (e.g. weekday_only = Friday only).
     */
    public function isWheelAvailableNow(int $userId): bool
    {
        $config = $this->getConfig();
        if (!$config || !$config->isActive()) {
            return false;
        }

        $type = SpinWheelPeriodType::tryFrom($config->period_type);
        if ($type !== SpinWheelPeriodType::EVERY_WEEKDAY || !$config->weekday_only) {
            return true;
        }

        $bounds = $this->getCurrentPeriodBounds($userId);
        return $bounds !== null;
    }

    /**
     * Get or create user's current batch for the period.
     * For cooldown: reuse any unclaimed batch (no new batch until user claims); time does not close the batch.
     */
    public function getOrCreateUserBatch(int $userId): ?SpinWheelUserBatch
    {
        $config = $this->getConfig();
        if (!$config || !$config->isActive()) {
            return null;
        }

        if (!$this->isWheelAvailableNow($userId)) {
            return null;
        }

        $type = SpinWheelPeriodType::tryFrom($config->period_type);

        if ($type === SpinWheelPeriodType::COOLDOWN_DAYS) {
            $existingUnclaimed = SpinWheelUserBatch::where('user_id', $userId)
                ->where('status', '!=', SpinWheelUserBatch::STATUS_CLAIMED)
                ->orderByDesc('id')
                ->first();

            if ($existingUnclaimed) {
                return $existingUnclaimed;
            }
        }

        $bounds = $this->getCurrentPeriodBounds($userId);

        if (!$bounds && $type === SpinWheelPeriodType::COOLDOWN_DAYS) {
            $hasAnyBatch = SpinWheelUserBatch::where('user_id', $userId)->exists();
            if (!$hasAnyBatch) {
                $value = (int) ($config->period_value ?? 1);
                $tz = config('app.timezone');
                $now = Carbon::now($tz);
                $periodStart = $now->copy()->startOfDay();
                $periodEnd = $periodStart->copy()->addDays($value)->subSecond();
                $bounds = [$periodStart, $periodEnd];
            }
        }

        if (!$bounds) {
            return null;
        }

        [$periodStart, $periodEnd] = $bounds;

        $batch = SpinWheelUserBatch::where('user_id', $userId)
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->first();

        if ($batch) {
            if ($type !== SpinWheelPeriodType::COOLDOWN_DAYS
                && $batch->isExpired()
                && $batch->status === SpinWheelUserBatch::STATUS_ACTIVE) {
                $batch->update(['status' => SpinWheelUserBatch::STATUS_EXPIRED]);
            }
            return $batch;
        }

        return SpinWheelUserBatch::create([
            'user_id' => $userId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'spins_used' => 0,
            'status' => SpinWheelUserBatch::STATUS_ACTIVE,
        ]);
    }

    /**
     * Get eligible options for the wheel (active, under cap for this period).
     */
    public function getEligibleOptions(SpinWheelUserBatch $batch): \Illuminate\Support\Collection
    {
        $options = SpinWheelOption::where('is_active', true)
            ->orderBy('display_order')
            ->get();

        $periodStart = $batch->period_start;
        $periodEnd = $batch->period_end;

        return $options->filter(function (SpinWheelOption $opt) use ($periodStart, $periodEnd) {
            if (!$opt->max_claims_per_period) {
                return true;
            }
            $claimed = SpinWheelClaim::where('option_id', $opt->id)
                ->whereHas('batch', fn ($q) => $q->whereBetween('period_start', [$periodStart, $periodEnd]))
                ->count();
            return $claimed < $opt->max_claims_per_period;
        });
    }

    /**
     * Pick a random option by weight.
     */
    public function pickOptionByWeight(\Illuminate\Support\Collection $options): ?SpinWheelOption
    {
        if ($options->isEmpty()) {
            return null;
        }
        $total = $options->sum('weight');
        if ($total <= 0) {
            return $options->first();
        }
        $r = mt_rand(1, (int) $total);
        $cum = 0;
        foreach ($options as $opt) {
            $cum += $opt->weight;
            if ($r <= $cum) {
                return $opt;
            }
        }
        return $options->last();
    }

    /**
     * Spin the wheel: server decides outcome, stores result, returns it.
     */
    public function spin(int $userId): array
    {
        $config = $this->getConfig();
        if (!$config || !$config->isActive()) {
            throw ValidationException::withMessages(['spin_wheel' => 'Spin wheel is not active.']);
        }

        $batch = $this->getOrCreateUserBatch($userId);
        if (!$batch) {
            throw ValidationException::withMessages(['spin_wheel' => 'Spin wheel is not available at this time.']);
        }

        if ($batch->status !== SpinWheelUserBatch::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['spin_wheel' => 'You have already claimed or this period has ended.']);
        }

        $maxSpins = (int) $config->max_spins_per_period;
        if ($batch->spins_used >= $maxSpins) {
            throw ValidationException::withMessages(['spin_wheel' => 'No spins left for this period.']);
        }

        $options = $this->getEligibleOptions($batch);
        if ($options->isEmpty()) {
            throw ValidationException::withMessages(['spin_wheel' => 'No rewards available at the moment.']);
        }

        $option = $this->pickOptionByWeight($options);
        if (!$option) {
            throw ValidationException::withMessages(['spin_wheel' => 'Unable to spin.']);
        }

        return DB::transaction(function () use ($userId, $batch, $option, $maxSpins) {
            $spinsUsed = $batch->spins_used + 1;
            $rewardData = $option->getRewardValueForDisplay();

            SpinWheelSpinHistory::create([
                'user_id' => $userId,
                'batch_id' => $batch->id,
                'option_id' => $option->id,
                'reward_type' => $option->reward_type,
                'reward_value' => $rewardData,
                'spin_number' => $spinsUsed,
                'spun_at' => now(),
            ]);

            $batch->update([
                'spins_used' => $spinsUsed,
                'current_result_option_id' => $option->id,
                'current_result_reward_data' => $rewardData,
            ]);

            return [
                'batch' => $batch->fresh(),
                'reward' => $rewardData,
                'option' => $option,
                'spins_remaining' => max(0, $maxSpins - $spinsUsed),
                'can_choose' => true,
            ];
        });
    }

    /**
     * User chooses the current result: grant reward and end period.
     */
    public function choose(int $userId): array
    {
        $config = $this->getConfig();
        if (!$config || !$config->isActive()) {
            throw ValidationException::withMessages(['spin_wheel' => 'Spin wheel is not active.']);
        }

        $batch = $this->getOrCreateUserBatch($userId);
        if (!$batch) {
            throw ValidationException::withMessages(['spin_wheel' => 'Spin wheel is not available.']);
        }

        if ($batch->status !== SpinWheelUserBatch::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['spin_wheel' => 'You have already claimed or this period has ended.']);
        }

        if (!$batch->current_result_option_id) {
            throw ValidationException::withMessages(['spin_wheel' => 'No result to choose. Spin first.']);
        }

        $option = SpinWheelOption::find($batch->current_result_option_id);
        if (!$option) {
            throw ValidationException::withMessages(['spin_wheel' => 'Invalid result.']);
        }

        return DB::transaction(function () use ($userId, $batch, $option) {
            $rewardType = SpinWheelRewardType::tryFrom($option->reward_type);
            $rewardValue = $batch->current_result_reward_data ?? $option->getRewardValueForDisplay();

            $status = $rewardType === SpinWheelRewardType::POINTS ? SpinWheelClaim::STATUS_GRANTED : SpinWheelClaim::STATUS_PENDING;

            SpinWheelClaim::create([
                'user_id' => $userId,
                'batch_id' => $batch->id,
                'option_id' => $option->id,
                'reward_type' => $option->reward_type,
                'reward_value' => $rewardValue,
                'status' => $status,
                'created_at' => now(),
            ]);

            if ($rewardType === SpinWheelRewardType::POINTS) {
                $points = (float) ($option->value ?? 0);
                if ($points > 0) {
                    $this->pointsService->grantSpinWheelPoints(
                        userId: $userId,
                        points: $points,
                        source: $batch,
                        description: 'Spin wheel reward: ' . (int) $points . ' points',
                        metadata: [
                            'spin_wheel_batch_id' => $batch->id,
                            'spin_wheel_option_id' => $option->id,
                        ]
                    );
                }
            }

            $batch->update([
                'status' => SpinWheelUserBatch::STATUS_CLAIMED,
                'claimed_option_id' => $option->id,
                'claimed_at' => now(),
            ]);

            return [
                'batch' => $batch->fresh(),
                'reward' => $rewardValue,
                'reward_type' => $option->reward_type,
                'status' => $status,
            ];
        });
    }

    /**
     * Get user's spin wheel status for frontend.
     */
    public function getStatus(int $userId): array
    {
        $config = $this->getConfig();
        $active = $config && $config->isActive();
        $available = $active && $this->isWheelAvailableNow($userId);
        $batch = $available ? $this->getOrCreateUserBatch($userId) : null;

        $options = [];
        if ($config && $active) {
            $options = SpinWheelOption::where('is_active', true)
                ->orderBy('display_order')
                ->with('product')
                ->get()
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'label' => $o->label,
                    'reward' => $o->getRewardValueForDisplay(),
                    'weight' => $o->weight,
                ])
                ->values()
                ->all();
        }

        $currentResult = null;
        $spinsRemaining = 0;
        $canSpin = false;
        $canChoose = false;
        $periodStart = null;
        $periodEnd = null;

        if ($batch) {
            $maxSpins = (int) ($config->max_spins_per_period ?? 3);
            $spinsRemaining = max(0, $maxSpins - $batch->spins_used);
            $canSpin = $batch->status === SpinWheelUserBatch::STATUS_ACTIVE && $spinsRemaining > 0;
            $canChoose = $batch->canChoose();
            $currentResult = $batch->current_result_reward_data;
            $periodStart = $batch->period_start?->toIso8601String();
            $periodEnd = $batch->period_end?->toIso8601String();
        }

        return [
            'is_active' => $active,
            'is_available_now' => $available,
            'options' => $options,
            'batch' => $batch ? [
                'id' => $batch->id,
                'spins_used' => $batch->spins_used,
                'spins_remaining' => $spinsRemaining,
                'current_result' => $currentResult,
                'can_spin' => $canSpin,
                'can_choose' => $canChoose,
                'status' => $batch->status,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ] : null,
        ];
    }

    /**
     * Get user's spin history.
     */
    public function getHistory(int $userId, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return SpinWheelSpinHistory::where('user_id', $userId)
            ->with(['option', 'batch'])
            ->orderByDesc('spun_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get all users' spin history (for admin).
     */
    public function getHistoryForAdmin(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return SpinWheelSpinHistory::query()
            ->with(['option', 'batch', 'user:id,name,username'])
            ->orderByDesc('spun_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get user's claimed rewards (including pending fulfillments).
     */
    public function getClaims(int $userId, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return SpinWheelClaim::where('user_id', $userId)
            ->with(['option', 'batch', 'fulfilledByUser'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
