<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spin_wheel_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('spin_wheel_user_batches')->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('spin_wheel_options')->cascadeOnDelete();
            $table->string('reward_type');
            $table->json('reward_value')->nullable();
            $table->string('status', 20)->default('pending'); // granted (points), pending (non-points), fulfilled (admin marked)
            $table->foreignId('fulfilled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable();
            $table->text('fulfillment_notes')->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index(['option_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spin_wheel_claims');
    }
};
