<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin user
        User::create([
            'name' => 'Admin User',
            'username' => 'admin_1',
            'email' => 'admin@admin.com',
            'phone' => '01207889825',
            'password' => Hash::make('admin'),
            'role' => UserRole::ADMIN->value,
            'status' => UserStatus::ACTIVE->value,
            'email_verified_at' => now(),
        ]);

        // Staff users
        User::create([
            'name' => 'Staff Member One',
            'username' => 'staff1',
            'email' => 'staff1@example.com',
            'phone' => '01234567890',
            'password' => Hash::make('password123'),
            'role' => UserRole::STAFF->value,
            'status' => UserStatus::ACTIVE->value,
        ]);

        User::create([
            'name' => 'Staff Member Two',
            'username' => 'staff2',
            'email' => 'staff2@example.com',
            'phone' => '01234567891',
            'password' => Hash::make('password123'),
            'role' => UserRole::STAFF->value,
            'status' => UserStatus::ACTIVE->value,
        ]);

        // Customer users
        User::create([
            'name' => 'John Customer',
            'username' => 'johncustomer',
            'email' => 'john@customer.com',
            'phone' => '01111111111',
            'password' => Hash::make('password123'),
            'role' => UserRole::CUSTOMER->value,
            'status' => UserStatus::ACTIVE->value,
        ]);

        User::create([
            'name' => 'Jane Customer',
            'username' => 'janecustomer',
            'email' => 'jane@customer.com',
            'phone' => '01222222222',
            'password' => Hash::make('password123'),
            'role' => UserRole::CUSTOMER->value,
            'status' => UserStatus::ACTIVE->value,
        ]);

        // Guest users
        User::create([
            'name' => 'Guest One',
            'username' => 'guest1',
            'email' => 'guest1@example.com',
            'phone' => '01333333333',
            'password' => Hash::make('password123'),
            'role' => UserRole::GUEST->value,
            'status' => UserStatus::ACTIVE->value,
        ]);

        User::create([
            'name' => 'Guest Two',
            'username' => 'guest2',
            'email' => 'guest2@example.com',
            'phone' => '01444444444',
            'password' => Hash::make('password123'),
            'role' => UserRole::GUEST->value,
            'status' => UserStatus::ACTIVE->value,
        ]);
    }
}