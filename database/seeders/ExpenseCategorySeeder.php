<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Seed the "Products" main category for product expenses.
     */
    public function run(): void
    {
        if (ExpenseCategory::where('name', 'Products')->whereNull('parent_id')->exists()) {
            return;
        }

        ExpenseCategory::create([
            'name' => 'Products',
            'parent_id' => null,
            'is_active' => true,
        ]);
    }
}
