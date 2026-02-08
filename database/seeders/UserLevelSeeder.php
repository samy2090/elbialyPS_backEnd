<?php

namespace Database\Seeders;

use App\Models\UserLevel;
use Illuminate\Database\Seeder;

class UserLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['name' => 'Bronze', 'min_points' => 0, 'sort_order' => 1, 'perks' => null],
            ['name' => 'Silver', 'min_points' => 100, 'sort_order' => 2, 'perks' => ['discount_percent' => 5]],
            ['name' => 'Gold', 'min_points' => 500, 'sort_order' => 3, 'perks' => ['discount_percent' => 10]],
            ['name' => 'Platinum', 'min_points' => 1500, 'sort_order' => 4, 'perks' => ['discount_percent' => 15]],
        ];

        foreach ($levels as $level) {
            UserLevel::firstOrCreate(
                ['name' => $level['name']],
                $level
            );
        }
    }
}
