<?php

namespace Database\Seeders;

use App\Enums\SessionStatus;
use App\Enums\SessionType;
use App\Models\Session;
use App\Models\User;
use Illuminate\Database\Seeder;

class GameSessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get staff and customer users
        $staff1 = User::where('username', 'staff1')->first();
        $staff2 = User::where('username', 'staff2')->first();
        $customer1 = User::where('username', 'johncustomer')->first();
        $customer2 = User::where('username', 'janecustomer')->first();

        // Active session
        Session::create([
            'created_by' => $staff1->id,
            'customer_id' => $customer1->id,
            'started_at' => now()->subHours(2),
            'ended_at' => null,
            'status' => SessionStatus::ACTIVE->value,
            'type' => SessionType::PLAYING->value,
            'total_price' => 0,
            'discount' => 0,
            'updated_by' => $staff1->id,
        ]);

        // Paused session
        Session::create([
            'created_by' => $staff2->id,
            'customer_id' => $customer2->id,
            'started_at' => now()->subHours(4),
            'ended_at' => null,
            'status' => SessionStatus::PAUSED->value,
            'type' => SessionType::CHILLOUT->value,
            'total_price' => 150.00,
            'discount' => 10.00,
            'updated_by' => $staff2->id,
        ]);

        // Ended session
        Session::create([
            'created_by' => $staff1->id,
            'customer_id' => $customer1->id,
            'started_at' => now()->subDays(1)->subHours(3),
            'ended_at' => now()->subDays(1),
            'status' => SessionStatus::ENDED->value,
            'type' => SessionType::PLAYING->value,
            'total_price' => 250.00,
            'discount' => 25.00,
            'updated_by' => $staff1->id,
        ]);

        // Another active session
        Session::create([
            'created_by' => $staff2->id,
            'customer_id' => $customer2->id,
            'started_at' => now()->subHours(1),
            'ended_at' => null,
            'status' => SessionStatus::ACTIVE->value,
            'type' => SessionType::CHILLOUT->value,
            'total_price' => 0,
            'discount' => 0,
            'updated_by' => $staff2->id,
        ]);
    }
}
