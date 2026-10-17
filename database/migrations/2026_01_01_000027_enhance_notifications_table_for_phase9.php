<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'security_event_id')) {
                $table->foreignId('security_event_id')->nullable()->after('device_id')->constrained('security_events')->nullOnDelete();
            }
            if (! Schema::hasColumn('notifications', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('notifications', 'failure_reason')) {
                $table->text('failure_reason')->nullable()->after('sent_at');
            }
        });

        Schema::table('trusted_users', function (Blueprint $table) {
            if (! Schema::hasColumn('trusted_users', 'fcm_token')) {
                $table->string('fcm_token', 500)->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'security_event_id')) {
                $table->dropConstrainedForeignId('security_event_id');
            }
            $cols = [];
            if (Schema::hasColumn('notifications', 'sent_at')) {
                $cols[] = 'sent_at';
            }
            if (Schema::hasColumn('notifications', 'failure_reason')) {
                $cols[] = 'failure_reason';
            }
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('trusted_users', function (Blueprint $table) {
            if (Schema::hasColumn('trusted_users', 'fcm_token')) {
                $table->dropColumn('fcm_token');
            }
        });
    }
};
