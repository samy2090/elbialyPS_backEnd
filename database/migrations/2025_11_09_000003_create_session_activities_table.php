<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('session_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('game_sessions')->onDelete('cascade');
            $table->enum('type', ['playing', 'chillout'])->default('playing');
            $table->enum('activity_type', ['device_use', 'pause'])->default('device_use');
            $table->foreignId('device_id')->nullable()->constrained('devices')->onDelete('restrict');
            $table->enum('mode', ['single', 'multi'])->default('single');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->enum('status', ['active', 'paused', 'ended'])->default('active');
            $table->decimal('duration_hours', 5, 2)->nullable();
            $table->decimal('total_price', 10, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('session_activities');
    }
};