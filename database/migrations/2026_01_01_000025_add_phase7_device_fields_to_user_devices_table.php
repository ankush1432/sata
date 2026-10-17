<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('user_devices', 'manufacturer')) {
                $table->string('manufacturer', 100)->nullable()->after('model');
            }
            if (! Schema::hasColumn('user_devices', 'device_model')) {
                $table->string('device_model', 100)->nullable()->after('model');
            }
            if (! Schema::hasColumn('user_devices', 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable()->after('last_seen_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            if (Schema::hasColumn('user_devices', 'manufacturer')) {
                $table->dropColumn('manufacturer');
            }
            if (Schema::hasColumn('user_devices', 'device_model')) {
                $table->dropColumn('device_model');
            }
            if (Schema::hasColumn('user_devices', 'revoked_at')) {
                $table->dropColumn('revoked_at');
            }
        });
    }
};
