<?php

namespace Database\Seeders;

use App\Models\Session;
use App\Models\SessionUser;
use App\Models\User;
use Illuminate\Database\Seeder;

class SessionUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sessions = Session::all();
        $users = User::whereHas('role', function ($query) {
            $query->whereIn('name', ['customer', 'guest']);
        })->get();

        // Add users to sessions
        foreach ($sessions as $index => $session) {
            // Add customer to their session
            SessionUser::create([
                'session_id' => $session->id,
                'user_id' => $session->customer_id,
                'is_payer' => true,
            ]);

            // Add additional guest users to sessions (optional)
            if ($index % 2 == 0 && $users->count() > 2) {
                SessionUser::create([
                    'session_id' => $session->id,
                    'user_id' => $users->get(2)->id, // guest1
                    'is_payer' => false,
                ]);
            }

            if ($index % 3 == 0 && $users->count() > 3) {
                SessionUser::create([
                    'session_id' => $session->id,
                    'user_id' => $users->get(3)->id, // guest2
                    'is_payer' => false,
                ]);
            }
        }
    }
}
