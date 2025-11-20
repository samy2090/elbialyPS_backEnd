<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Note: We cannot use a simple unique constraint on device_id because:
        // 1. device_id can be null (for chillout activities)
        // 2. We only want uniqueness for active activities (not ended ones)
        // Device uniqueness is enforced in application logic via SessionActivity model
        // This migration is kept for backward compatibility but the constraint is not applied
    }

    public function down()
    {
        Schema::table('session_activities', function (Blueprint $table) {
            $table->dropUnique('unique_active_device_per_session');
        });
    }
};
