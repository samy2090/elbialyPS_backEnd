<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spin_wheel_spin_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('spin_wheel_user_batches')->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('spin_wheel_options')->cascadeOnDelete();
            $table->string('reward_type');
            $table->json('reward_value')->nullable();
            $table->unsignedTinyInteger('spin_number')->default(1); // 1, 2, or 3
            $table->timestamp('spun_at');
            $table->timestamps();

            $table->index(['user_id', 'spun_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spin_wheel_spin_history');
    }
};
