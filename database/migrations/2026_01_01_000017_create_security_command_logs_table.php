<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_command_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_command_id')->constrained('security_commands')->cascadeOnDelete();
            $table->string('status_before', 50)->nullable();
            $table->string('status_after', 50)->nullable()->index();
            $table->string('status_from', 50)->nullable();
            $table->string('status_to', 50)->nullable()->index();
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at')->nullable()->index();
            $table->timestamps();

            $table->index(['security_command_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_command_logs');
    }
};
