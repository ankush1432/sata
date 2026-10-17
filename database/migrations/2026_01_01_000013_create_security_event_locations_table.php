<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_event_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_event_id')->constrained('security_events')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7)->index();
            $table->decimal('longitude', 10, 7)->index();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('altitude', 8, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('heading', 8, 2)->nullable();
            $table->string('address')->nullable();
            $table->timestamp('recorded_at')->nullable()->index();
            $table->timestamp('captured_at')->nullable()->index();
            $table->timestamps();

            $table->index(['security_event_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_event_locations');
    }
};
