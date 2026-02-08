<?php

namespace App\Console\Commands;

use App\Enums\PointTransactionType;
use App\Models\ScorePointsSetting;
use App\Models\ScorePointsTransaction;
use App\Models\UserPointBalance;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPointsExpiry extends Command
{
    protected $signature = 'points:process-expiry';

    protected $description = 'Process points expiry - reset all user points on configured date (monthly or specific)';

    public function handle(): int
    {
        $config = ScorePointsSetting::getConfig();
        if (!$config || !$config->points_expiry_enabled) {
            $this->info('Points expiry is disabled.');
            return Command::SUCCESS;
        }

        $today = Carbon::today();

        if (!$this->shouldRunExpiry($config, $today)) {
            $this->info('Today does not match the configured expiry date.');
            return Command::SUCCESS;
        }

        $cacheKey = 'points_expiry_last_run_' . $today->toDateString();
        if (Cache::has($cacheKey)) {
            $this->info('Expiry already processed today.');
            return Command::SUCCESS;
        }

        $this->info('Processing points expiry...');

        $count = 0;
        try {
            $balances = UserPointBalance::where('total_points', '>', 0)->get();

            foreach ($balances as $balance) {
                $points = (float) $balance->total_points;
                if ($points <= 0) {
                    continue;
                }

                DB::transaction(function () use ($balance, $points, &$count) {
                    ScorePointsTransaction::create([
                        'user_id' => $balance->user_id,
                        'points' => -$points,
                        'type' => PointTransactionType::EXPIRY,
                        'source_type' => null,
                        'source_id' => null,
                        'description' => 'Points expired (scheduled reset)',
                        'metadata' => ['expired_at' => now()->toIso8601String()],
                    ]);

                    $balance->update(['total_points' => 0]);
                    $count++;
                });
            }

            Cache::put($cacheKey, true, now()->addDay());

            $this->info("Successfully expired points for {$count} user(s).");
        } catch (\Throwable $e) {
            Log::error('ProcessPointsExpiry failed', ['error' => $e->getMessage()]);
            $this->error('Points expiry failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function shouldRunExpiry(ScorePointsSetting $config, Carbon $today): bool
    {
        $type = $config->points_expiry_type;

        if ($type === 'monthly') {
            $dayOfMonth = (int) $config->points_expiry_day_of_month;
            return $today->day === $dayOfMonth;
        }

        if ($type === 'specific_date') {
            $specificDate = $config->points_expiry_specific_date;
            return $specificDate && $today->isSameDay($specificDate);
        }

        return false;
    }
}
