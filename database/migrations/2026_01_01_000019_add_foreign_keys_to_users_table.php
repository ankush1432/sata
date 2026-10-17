<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('active_subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->nullOnDelete();

            $table->foreign('current_device_id')
                ->references('id')
                ->on('user_devices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['active_subscription_id']);
            $table->dropForeign(['current_device_id']);
        });
    }
};
