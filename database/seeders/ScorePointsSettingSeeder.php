<?php

namespace Database\Seeders;

use App\Models\ScorePointsSetting;
use Illuminate\Database\Seeder;

class ScorePointsSettingSeeder extends Seeder
{
    public function run(): void
    {
        ScorePointsSetting::updateOrCreate(
            ['id' => 1],
            [
                'is_active' => true,
                'points_per_hour' => 10,
                'points_money_threshold' => 50,
                'points_per_threshold' => 5,
                'points_expiry_enabled' => false,
                'points_expiry_type' => null,
                'points_expiry_day_of_month' => null,
                'points_expiry_specific_date' => null,
            ]
        );
    }
}
