<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spin_wheel_options', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('reward_type'); // points, percent_discount, free_minutes, free_product
            $table->decimal('value', 10, 2)->nullable(); // points amount, percent, or minutes
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete(); // for free_product
            $table->unsignedInteger('weight')->default(1); // probability weight
            $table->unsignedInteger('max_claims_per_period')->nullable(); // cap across all users per period (10 = max 10)
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spin_wheel_options');
    }
};
