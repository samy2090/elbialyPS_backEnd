<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\ActivityMode;
use App\Enums\SessionStatus;
use App\Models\Device;
use App\Models\Session;
use App\Models\SessionActivity;
use App\Models\User;
use Illuminate\Database\Seeder;

class SessionActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sessions = Session::all();
        // Only use available devices to avoid status conflicts
        $availableDevices = Device::where('status', 'available')->get();
        $staff1 = User::where('username', 'staff1')->first();
        $staff2 = User::where('username', 'staff2')->first();

        // Create activities for each session
        foreach ($sessions as $index => $session) {
            // Skip if no available devices
            if ($availableDevices->isEmpty()) {
                break;
            }
            
            $device = $availableDevices->get($index % $availableDevices->count());
            $createdBy = $index % 2 == 0 ? $staff1->id : $staff2->id;

            // Device use activity
            SessionActivity::create([
                'session_id' => $session->id,
                'activity_type' => ActivityType::DEVICE_USE->value,
                'device_id' => $device->id,
                'mode' => $index % 2 == 0 ? ActivityMode::SINGLE->value : ActivityMode::MULTI->value,
                'started_at' => $session->started_at,
                'ended_at' => $session->status === 'ended' ? $session->ended_at : null,
                'status' => $session->status,
                'duration_hours' => $session->status === 'ended' ? 3.5 : null,
                'price_per_hour' => 50.00,
                'total_price' => $session->status === 'ended' ? 175.00 : 0,
                'created_by' => $createdBy,
                'updated_by' => $createdBy,
            ]);

            // Pause activity (for ended sessions)
            if ($session->status === 'ended') {
                SessionActivity::create([
                    'session_id' => $session->id,
                    'activity_type' => ActivityType::PAUSE->value,
                    'device_id' => null,
                    'mode' => ActivityMode::SINGLE->value,
                    'started_at' => $session->ended_at,
                    'ended_at' => null,
                    'status' => SessionStatus::PAUSED->value,
                    'duration_hours' => null,
                    'price_per_hour' => null,
                    'total_price' => 0,
                    'created_by' => $createdBy,
                    'updated_by' => $createdBy,
                ]);
            }
        }
    }
}
