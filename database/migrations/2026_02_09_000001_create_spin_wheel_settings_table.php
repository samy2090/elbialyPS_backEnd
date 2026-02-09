<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spin_wheel_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(false);
            $table->string('period_type'); // every_n_days, every_weekday, every_month, cooldown_days
            $table->unsignedTinyInteger('period_value')->nullable(); // 1-7 for n_days or weekday (0=Sun..6=Sat), or cooldown days
            $table->boolean('weekday_only')->default(false); // for every_weekday: true = open only on that weekday, false = whole week
            $table->date('start_date')->nullable(); // fixed start for every_n_days / every_weekday / every_month
            $table->unsignedTinyInteger('max_spins_per_period')->default(3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spin_wheel_settings');
    }
};
