<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Phase 16: Multi-stage subscription reminder tracking for idempotency.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'reminder_stages_sent')) {
                $table->json('reminder_stages_sent')->nullable()->after('expired_reminder_sent_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'reminder_stages_sent')) {
                $table->dropColumn('reminder_stages_sent');
            }
        });
    }
};
