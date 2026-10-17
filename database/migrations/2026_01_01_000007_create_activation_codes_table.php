<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activation_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique()->index();
            $table->foreignId('activation_plan_id')->nullable()->constrained('activation_plans')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('activation_plans')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('activation_code_batches')->nullOnDelete();
            $table->string('status', 30)->default('UNUSED')->index();
            $table->boolean('is_redeemed')->default(false)->index();
            $table->foreignId('redeemed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_redeemed', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activation_codes');
    }
};
