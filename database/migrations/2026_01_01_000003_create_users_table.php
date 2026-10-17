<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone_number', 30)->unique()->index();
            $table->string('email')->nullable()->unique()->index();
            $table->string('password')->nullable();
            $table->string('status', 50)->default('PENDING')->index();
            $table->unsignedBigInteger('current_subscription_id')->nullable()->index();
            $table->unsignedBigInteger('active_subscription_id')->nullable()->index();
            $table->unsignedBigInteger('current_device_id')->nullable()->index();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
