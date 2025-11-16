<?php

namespace Database\Seeders;

use App\Models\ActivityProduct;
use App\Models\Product;
use App\Models\SessionActivity;
use App\Models\User;
use Illuminate\Database\Seeder;

class ActivityProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $activities = SessionActivity::where('activity_type', 'device_use')->get();
        $products = Product::all();
        $users = User::whereHas('role', function ($query) {
            $query->whereIn('name', ['customer', 'guest']);
        })->get();

        foreach ($activities as $activity) {
            // Add 1-3 products per activity
            $productCount = rand(1, 3);

            for ($i = 0; $i < $productCount; $i++) {
                $product = $products->random();
                $quantity = rand(1, 5);
                $price = $product->price ?? 25.00;
                $totalPrice = $price * $quantity;

                ActivityProduct::create([
                    'session_activity_id' => $activity->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total_price' => $totalPrice,
                    'ordered_by_user_id' => $users->random()->id,
                ]);
            }
        }
    }
}
