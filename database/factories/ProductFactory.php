<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = $this->faker->randomElement(['drink', 'snack']);
        $cost = $this->faker->randomFloat(2, 5, 50);
        $price = $cost * $this->faker->randomFloat(2, 1.5, 3); // Price is typically 1.5-3x the cost
        
        return [
            'name' => $this->faker->words(2, true),
            'sku' => strtoupper($this->faker->unique()->bothify('??###')),
            'note' => $this->faker->optional(0.3)->sentence(),
            'category' => $category,
            'price' => round($price, 2),
            'cost' => round($cost, 2),
            'is_active' => $this->faker->boolean(85), // 85% chance of being active
            'stock' => $this->faker->numberBetween(0, 100),
        ];
    }
}
