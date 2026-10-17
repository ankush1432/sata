<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_commands', function (Blueprint $table) {
            if (!Schema::hasColumn('security_commands', 'nonce')) {
                $table->string('nonce', 64)->nullable()->index()->after('command_uuid');
            }
            if (!Schema::hasColumn('security_commands', 'device_management_mode')) {
                $table->string('device_management_mode', 50)->nullable()->after('status');
            }
            if (!Schema::hasColumn('security_commands', 'issued_at')) {
                $table->timestamp('issued_at')->nullable()->after('requested_by_id');
            }
            if (!Schema::hasColumn('security_commands', 'acknowledged_at')) {
                $table->timestamp('acknowledged_at')->nullable()->after('sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('security_commands', function (Blueprint $table) {
            if (Schema::hasColumn('security_commands', 'nonce')) {
                $table->dropColumn('nonce');
            }
            if (Schema::hasColumn('security_commands', 'device_management_mode')) {
                $table->dropColumn('device_management_mode');
            }
            if (Schema::hasColumn('security_commands', 'issued_at')) {
                $table->dropColumn('issued_at');
            }
            if (Schema::hasColumn('security_commands', 'acknowledged_at')) {
                $table->dropColumn('acknowledged_at');
            }
        });
    }
};
