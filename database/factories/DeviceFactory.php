<?php

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Enums\DeviceType;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Device>
 */
class DeviceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Device::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $deviceType = $this->faker->randomElement(DeviceType::cases());
        
        return [
            'name' => $this->generateDeviceName($deviceType),
            'description' => $this->faker->sentence(10),
            'device_type' => $deviceType->value,
            'status' => $this->faker->randomElement(DeviceStatus::cases())->value,
            'price_per_hour' => $this->faker->randomFloat(2, 10.00, 50.00), // Price in EGP
            'multi_price' => $this->faker->optional(0.7)->randomFloat(2, 80.00, 200.00), // Price in EGP
            'notes' => $this->faker->optional(0.3)->sentence(),
        ];
    }

    /**
     * Generate a device name based on type
     */
    private function generateDeviceName(DeviceType $type): string
    {
        return match ($type) {
            DeviceType::PS4 => 'PlayStation 4 #' . $this->faker->numberBetween(1, 10),
            DeviceType::PS5 => 'PlayStation 5 #' . $this->faker->numberBetween(1, 5),
            DeviceType::BILLIARD => 'Billiard #' . $this->faker->numberBetween(1, 20),
        };
    }

    /**
     * Indicate that the device is available.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeviceStatus::AVAILABLE->value,
        ]);
    }

    /**
     * Indicate that the device is in use.
     */
    public function inUse(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeviceStatus::IN_USE->value,
        ]);
    }

    /**
     * Indicate that the device is in maintenance.
     */
    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DeviceStatus::MAINTENANCE->value,
        ]);
    }

    /**
     * Indicate that the device is a PS4.
     */
    public function ps4(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_type' => DeviceType::PS4->value,
            'name' => 'PlayStation 4 #' . $this->faker->numberBetween(1, 10),
        ]);
    }

    /**
     * Indicate that the device is a PS5.
     */
    public function ps5(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_type' => DeviceType::PS5->value,
            'name' => 'PlayStation 5 #' . $this->faker->numberBetween(1, 5),
        ]);
    }

    /**
     * Indicate that the device is a billiard.
     */
    public function billiard(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_type' => DeviceType::BILLIARD->value,
            'name' => 'Billiard #' . $this->faker->numberBetween(1, 20),
        ]);
    }
}
