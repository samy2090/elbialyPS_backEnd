<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('session_activities', function (Blueprint $table) {
            // Add unique constraint: a device can only be in one active session activity
            // This ensures no device can be used in multiple sessions simultaneously
            $table->unique(['device_id'], 'unique_active_device_per_session');
        });
    }

    public function down()
    {
        Schema::table('session_activities', function (Blueprint $table) {
            $table->dropUnique('unique_active_device_per_session');
        });
    }
};
