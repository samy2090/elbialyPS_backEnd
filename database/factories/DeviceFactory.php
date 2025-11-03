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
            'price_per_hour' => $this->faker->numberBetween(1000, 5000), // Price in cents
            'multi_price' => $this->faker->optional(0.7)->numberBetween(8000, 20000), // Price in cents
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
            DeviceType::BILLBOARD => 'Billboard #' . $this->faker->numberBetween(1, 20),
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
     * Indicate that the device is a billboard.
     */
    public function billboard(): static
    {
        return $this->state(fn (array $attributes) => [
            'device_type' => DeviceType::BILLBOARD->value,
            'name' => 'Billboard #' . $this->faker->numberBetween(1, 20),
        ]);
    }
}
