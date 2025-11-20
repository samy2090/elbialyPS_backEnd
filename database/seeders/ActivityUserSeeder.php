<?php

namespace Database\Seeders;

use App\Models\ActivityUser;
use App\Models\SessionActivity;
use App\Models\User;
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
            // Get the session for this activity
            $session = $activity->session;
            
            // Add the customer as a user to this activity
            ActivityUser::firstOrCreate(
                [
                    'session_activity_id' => $activity->id,
                    'user_id' => $session->customer_id,
                ],
                [
                    'duration_hours' => $activity->duration_hours,
                    'cost_share' => $activity->total_price,
                ]
            );

            // Optionally add additional users (staff or others) to activities
            // This can be expanded based on your business logic
        }
    }
}
