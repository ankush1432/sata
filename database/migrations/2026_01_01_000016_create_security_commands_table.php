<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('user_devices')->cascadeOnDelete();
            $table->string('command_uuid', 64)->unique()->index();
            $table->string('command_type', 50)->default('LOCK_DEVICE')->index();
            $table->string('status', 50)->default('PENDING')->index();
            $table->json('payload')->nullable();
            $table->foreignId('requested_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('requested_by_type', 30)->default('USER')->index();
            $table->unsignedBigInteger('requested_by_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('response_payload')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_commands');
    }
};
