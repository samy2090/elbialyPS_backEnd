<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_mode_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_activity_id')->constrained('session_activities')->onDelete('cascade');
            $table->enum('from_mode', ['single', 'multi'])->nullable(); // null for initial mode
            $table->enum('to_mode', ['single', 'multi']);
            $table->timestamp('changed_at');
            $table->timestamp('ended_at')->nullable(); // When this mode period ended (next change or activity end)
            $table->decimal('duration_minutes', 8, 2)->nullable(); // Calculated duration in minutes
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index('session_activity_id');
            $table->index('changed_at');
            $table->index(['session_activity_id', 'ended_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_mode_changes');
    }
};
