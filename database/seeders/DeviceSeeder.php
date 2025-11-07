<?php

namespace Database\Seeders;

use App\Enums\DeviceStatus;
use App\Enums\DeviceType;
use App\Models\Device;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create specific devices
        $devices = [
            [
                'name' => 'PlayStation 4 Console #1',
                'description' => 'Gaming console with multiple controllers available',
                'device_type' => DeviceType::PS4->value,
                'status' => DeviceStatus::AVAILABLE->value,
                'price_per_hour' => 2500, // 25.00 in cents
                'multi_price' => 12000, // 120.00 for multiple hours
                'notes' => 'Includes 2 controllers and headset',
            ],
            [
                'name' => 'PlayStation 4 Console #2',
                'description' => 'Gaming console with FIFA, Call of Duty pre-installed',
                'device_type' => DeviceType::PS4->value,
                'status' => DeviceStatus::AVAILABLE->value,
                'price_per_hour' => 2500,
                'multi_price' => 12000,
                'notes' => 'Popular games collection available',
            ],
            [
                'name' => 'PlayStation 5 Console #1',
                'description' => 'Latest generation gaming console with enhanced graphics',
                'device_type' => DeviceType::PS5->value,
                'status' => DeviceStatus::AVAILABLE->value,
                'price_per_hour' => 4000, // 40.00
                'multi_price' => 18000, // 180.00
                'notes' => 'DualSense controller included, 4K gaming',
            ],
            [
                'name' => 'PlayStation 5 Console #2',
                'description' => 'Premium gaming experience with ray tracing support',
                'device_type' => DeviceType::PS5->value,
                'status' => DeviceStatus::IN_USE->value,
                'price_per_hour' => 4000,
                'multi_price' => 18000,
                'notes' => 'Latest games collection',
            ],
            [
                'name' => 'Digital Billiard Table #1',
                'description' => 'Professional billiard table for gaming',
                'device_type' => DeviceType::BILLIARD->value,
                'status' => DeviceStatus::AVAILABLE->value,
                'price_per_hour' => 15000, // 150.00
                'multi_price' => 100000, // 1000.00 for extended periods
                'notes' => 'High resolution, weather resistant',
            ],
            [
                'name' => 'Digital Billiard Table #2',
                'description' => 'Premium billiard table with high-quality accessories',
                'device_type' => DeviceType::BILLIARD->value,
                'status' => DeviceStatus::MAINTENANCE->value,
                'price_per_hour' => 20000, // 200.00
                'multi_price' => 120000, // 1200.00
                'notes' => 'Prime location, maintenance scheduled for next week',
            ],
        ];

        foreach ($devices as $deviceData) {
            Device::create($deviceData);
        }

        // Create additional random devices using the factory
        Device::factory()->ps4()->available()->count(3)->create();
        Device::factory()->ps5()->available()->count(2)->create();
        Device::factory()->billiard()->available()->count(2)->create();
        
        // Create some devices in different states
        Device::factory()->ps4()->inUse()->count(1)->create();
        Device::factory()->ps5()->maintenance()->count(1)->create();
        Device::factory()->billiard()->inUse()->count(1)->create();
    }
}
