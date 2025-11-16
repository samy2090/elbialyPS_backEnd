<?php

namespace Database\Seeders;

use App\Models\ActivityUser;
use App\Models\SessionActivity;
use App\Models\SessionUser;
use Illuminate\Database\Seeder;

class ActivityUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $activities = SessionActivity::where('activity_type', 'device_use')->get();

        foreach ($activities as $activity) {
            // Get users in this session
            $sessionUsers = SessionUser::where('session_id', $activity->session_id)
                ->with('user')
                ->get();

            foreach ($sessionUsers as $sessionUser) {
                ActivityUser::create([
                    'session_activity_id' => $activity->id,
                    'user_id' => $sessionUser->user_id,
                    'duration_hours' => $activity->duration_hours ? $activity->duration_hours / $sessionUsers->count() : null,
                    'cost_share' => $activity->total_price ? $activity->total_price / $sessionUsers->count() : null,
                ]);
            }
        }
    }
}
