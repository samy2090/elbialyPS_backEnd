<?php

namespace Database\Seeders;

use App\Enums\SpinWheelPeriodType;
use App\Enums\SpinWheelRewardType;
use App\Models\SpinWheelOption;
use App\Models\SpinWheelSetting;
use Illuminate\Database\Seeder;

class SpinWheelSeeder extends Seeder
{
    public function run(): void
    {
        SpinWheelSetting::updateOrCreate(
            ['id' => 1],
            [
                'is_active' => false,
                'period_type' => SpinWheelPeriodType::EVERY_N_DAYS->value,
                'period_value' => 1,
                'weekday_only' => false,
                'start_date' => now()->toDateString(),
                'max_spins_per_period' => 3,
            ]
        );

        $options = [
            ['label' => '10 Points', 'reward_type' => SpinWheelRewardType::POINTS->value, 'value' => 10, 'weight' => 5, 'display_order' => 1],
            ['label' => '25 Points', 'reward_type' => SpinWheelRewardType::POINTS->value, 'value' => 25, 'weight' => 3, 'display_order' => 2],
            ['label' => '50 Points', 'reward_type' => SpinWheelRewardType::POINTS->value, 'value' => 50, 'weight' => 2, 'display_order' => 3],
            ['label' => '100 Points', 'reward_type' => SpinWheelRewardType::POINTS->value, 'value' => 100, 'weight' => 1, 'display_order' => 4],
            ['label' => '10% Off', 'reward_type' => SpinWheelRewardType::PERCENT_DISCOUNT->value, 'value' => 10, 'weight' => 2, 'max_claims_per_period' => 10, 'display_order' => 5],
            ['label' => '30 Free Minutes', 'reward_type' => SpinWheelRewardType::FREE_MINUTES->value, 'value' => 30, 'weight' => 1, 'max_claims_per_period' => 5, 'display_order' => 6],
        ];

        foreach ($options as $i => $opt) {
            SpinWheelOption::updateOrCreate(
                ['id' => $i + 1],
                array_merge($opt, ['is_active' => true])
            );
        }
    }
}
