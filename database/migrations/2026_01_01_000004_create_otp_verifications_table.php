<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('phone_number', 30)->nullable()->index();
            $table->string('phone', 30)->nullable()->index();
            $table->string('otp_code', 10)->nullable();
            $table->string('code_hash')->nullable();
            $table->string('purpose', 50)->default('LOGIN')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->boolean('is_verified')->default(false)->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['phone_number', 'is_verified', 'expires_at'], 'otp_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};
