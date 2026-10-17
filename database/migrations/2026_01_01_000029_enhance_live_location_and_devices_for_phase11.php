<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add location columns to user_devices if not present
        Schema::table('user_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('user_devices', 'last_latitude')) {
                $table->decimal('last_latitude', 10, 7)->nullable()->after('status');
                $table->decimal('last_longitude', 10, 7)->nullable()->after('last_latitude');
                $table->decimal('last_accuracy', 8, 2)->nullable()->after('last_longitude');
                $table->decimal('last_altitude', 8, 2)->nullable()->after('last_accuracy');
                $table->decimal('last_speed', 8, 2)->nullable()->after('last_altitude');
                $table->decimal('last_heading', 8, 2)->nullable()->after('last_speed');
                $table->timestamp('location_updated_at')->nullable()->after('last_heading');
            }
        });

        // 2. Enhance live_location_sessions
        Schema::table('live_location_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('live_location_sessions', 'stopped_at')) {
                $table->timestamp('stopped_at')->nullable()->after('expires_at');
            }
            if (! Schema::hasColumn('live_location_sessions', 'status')) {
                $table->string('status', 30)->default('ACTIVE')->index()->after('stopped_at');
            }
            if (! Schema::hasColumn('live_location_sessions', 'short_token')) {
                $table->string('short_token', 64)->nullable()->unique()->after('session_token');
            }
            if (! Schema::hasColumn('live_location_sessions', 'last_altitude')) {
                $table->decimal('last_altitude', 8, 2)->nullable()->after('last_accuracy');
                $table->decimal('last_speed', 8, 2)->nullable()->after('last_altitude');
                $table->decimal('last_heading', 8, 2)->nullable()->after('last_speed');
            }
        });

        // 3. Create location_access_audit_logs for sensitive location tracking audit
        if (! Schema::hasTable('location_access_audit_logs')) {
            Schema::create('location_access_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('target_device_id')->nullable()->constrained('user_devices')->nullOnDelete();
                $table->string('session_identifier', 64)->nullable()->index();
                $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('accessor_type', 32)->index(); // ADMIN, USER, TRUSTED_USER, SHORT_URL
                $table->unsignedBigInteger('accessor_id')->nullable()->index();
                $table->string('action', 64)->index(); // LOCATION_VIEWED, LIVE_SESSION_VIEWED, etc.
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['target_user_id', 'action']);
                $table->index(['created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('location_access_audit_logs');

        Schema::table('live_location_sessions', function (Blueprint $table) {
            $columns = ['stopped_at', 'status', 'short_token', 'last_altitude', 'last_speed', 'last_heading'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('live_location_sessions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('user_devices', function (Blueprint $table) {
            $columns = ['last_latitude', 'last_longitude', 'last_accuracy', 'last_altitude', 'last_speed', 'last_heading', 'location_updated_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('user_devices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
