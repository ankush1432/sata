<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to add optimized composite indexes for high-throughput dashboard queries.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'idx_users_status_created_at');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['status', 'expires_at'], 'idx_subscriptions_status_expires');
            $table->index(['status', 'starts_at'], 'idx_subscriptions_status_starts');
        });

        Schema::table('user_devices', function (Blueprint $table) {
            $table->index(['status', 'last_seen_at'], 'idx_devices_status_last_seen');
        });

        Schema::table('security_events', function (Blueprint $table) {
            $table->index(['event_type', 'occurred_at'], 'idx_security_events_type_occurred');
        });

        Schema::table('security_commands', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'idx_security_commands_status_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_status_created_at');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_subscriptions_status_expires');
            $table->dropIndex('idx_subscriptions_status_starts');
        });

        Schema::table('user_devices', function (Blueprint $table) {
            $table->dropIndex('idx_devices_status_last_seen');
        });

        Schema::table('security_events', function (Blueprint $table) {
            $table->dropIndex('idx_security_events_type_occurred');
        });

        Schema::table('security_commands', function (Blueprint $table) {
            $table->dropIndex('idx_security_commands_status_created');
        });
    }
};
