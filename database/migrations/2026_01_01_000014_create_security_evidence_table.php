<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_evidence', function (Blueprint $table) {
            $table->id();
            $table->uuid('evidence_uuid')->nullable()->unique();
            $table->foreignId('security_event_id')->constrained('security_events')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('user_devices')->nullOnDelete();
            $table->string('evidence_type', 50)->default('FRONT_PHOTO')->index();
            $table->string('storage_disk')->default('private_evidence');
            $table->string('storage_path')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('checksum')->nullable();
            $table->string('sha256_hash', 64)->nullable()->index();
            $table->timestamp('captured_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['security_event_id', 'evidence_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_evidence');
    }
};
