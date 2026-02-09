<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spin_wheel_user_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->unsignedTinyInteger('spins_used')->default(0); // 0-3
            $table->foreignId('current_result_option_id')->nullable()->constrained('spin_wheel_options')->nullOnDelete();
            $table->json('current_result_reward_data')->nullable(); // cached reward for display
            $table->string('status', 20)->default('active'); // active, claimed, expired
            $table->foreignId('claimed_option_id')->nullable()->constrained('spin_wheel_options')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'period_start']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spin_wheel_user_batches');
    }
};
