<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trusted_user_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trusted_user_id')->constrained('trusted_users')->cascadeOnDelete();
            $table->string('token', 64)->nullable()->unique()->index();
            $table->string('verification_code', 30)->nullable()->index();
            $table->boolean('is_verified')->default(false)->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_user_verifications');
    }
};
