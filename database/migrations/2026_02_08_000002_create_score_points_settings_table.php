<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_points_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->decimal('points_per_hour', 10, 2)->default(10);
            $table->decimal('points_money_threshold', 10, 2)->default(50);
            $table->decimal('points_per_threshold', 10, 2)->default(5);
            $table->boolean('points_expiry_enabled')->default(false);
            $table->string('points_expiry_type', 50)->nullable();
            $table->integer('points_expiry_day_of_month')->nullable();
            $table->date('points_expiry_specific_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_points_settings');
    }
};
