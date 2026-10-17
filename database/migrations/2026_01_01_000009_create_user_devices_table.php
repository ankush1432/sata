<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_uuid', 100)->unique()->index();
            $table->string('device_name')->nullable();
            $table->string('model')->nullable();
            $table->string('platform', 30)->default('android')->index(); // android, ios
            $table->string('os_version', 50)->nullable();
            $table->string('app_version', 50)->nullable();
            $table->text('fcm_token')->nullable();
            $table->string('sim_serial', 100)->nullable()->index();
            $table->string('phone_number', 30)->nullable();
            $table->string('imei', 100)->nullable()->index();
            $table->string('status', 50)->default('ACTIVE')->index();
            $table->boolean('is_protection_enabled')->default(true)->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
