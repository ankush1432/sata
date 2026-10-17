<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('device_id')->nullable()->after('activation_code_id')->constrained('user_devices')->nullOnDelete();
            $table->timestamp('activated_at')->nullable()->after('starts_at');
            $table->timestamp('expiring_reminder_sent_at')->nullable()->after('cancelled_at');
            $table->timestamp('expired_reminder_sent_at')->nullable()->after('expiring_reminder_sent_at');
            $table->timestamp('disabled_at')->nullable()->after('expired_reminder_sent_at');
            $table->string('disable_reason')->nullable()->after('disabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('device_id');
            $table->dropColumn([
                'activated_at',
                'expiring_reminder_sent_at',
                'expired_reminder_sent_at',
                'disabled_at',
                'disable_reason',
            ]);
        });
    }
};
