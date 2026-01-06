<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('activity_pauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_activity_id')->constrained('session_activities')->onDelete('cascade');
            $table->timestamp('paused_at');
            $table->timestamp('resumed_at')->nullable();
            $table->decimal('pause_duration_minutes', 8, 2)->nullable();
            $table->foreignId('paused_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('resumed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index('session_activity_id');
            $table->index('paused_at');
            $table->index(['session_activity_id', 'resumed_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('activity_pauses');
    }
};
