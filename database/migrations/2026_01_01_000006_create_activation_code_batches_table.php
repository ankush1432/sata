<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activation_code_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 50)->unique()->index();
            $table->foreignId('activation_plan_id')->nullable()->constrained('activation_plans')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('activation_plans')->cascadeOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activation_code_batches');
    }
};
