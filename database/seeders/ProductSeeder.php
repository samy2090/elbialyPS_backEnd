<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create some sample products using updateOrCreate to avoid duplicates
        Product::updateOrCreate(
            ['sku' => 'COKE001'],
            [
                'name' => 'Coca Cola',
                'note' => 'Classic Coca Cola 330ml',
                'category' => 'drink',
                'price' => 15.00,
                'cost' => 8.00,
                'is_active' => true,
                'stock' => 50,
            ]
        );

        Product::updateOrCreate(
            ['sku' => 'PEPSI001'],
            [
                'name' => 'Pepsi',
                'note' => 'Pepsi Cola 330ml',
                'category' => 'drink',
                'price' => 15.00,
                'cost' => 8.00,
                'is_active' => true,
                'stock' => 45,
            ]
        );

        Product::updateOrCreate(
            ['sku' => 'CHIP001'],
            [
                'name' => 'Chips Ahoy',
                'note' => 'Chocolate chip cookies',
                'category' => 'snack',
                'price' => 25.00,
                'cost' => 12.00,
                'is_active' => true,
                'stock' => 30,
            ]
        );

        Product::updateOrCreate(
            ['sku' => 'OREO001'],
            [
                'name' => 'Oreo',
                'note' => 'Original Oreo cookies',
                'category' => 'snack',
                'price' => 20.00,
                'cost' => 10.00,
                'is_active' => true,
                'stock' => 25,
            ]
        );

        // Create 10 random products using factory only if we don't have enough products
        $currentCount = Product::count();
        if ($currentCount < 14) {
            $needed = 14 - $currentCount;
            Product::factory($needed)->create();
        }
    }
}
