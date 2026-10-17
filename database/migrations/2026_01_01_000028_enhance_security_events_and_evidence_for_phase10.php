<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('security_events', function (Blueprint $table) {
            if (! Schema::hasColumn('security_events', 'status')) {
                $table->string('status', 30)->default('REPORTED')->after('severity')->index();
            }
            if (! Schema::hasColumn('security_events', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('status')->index();
            }
            if (! Schema::hasColumn('security_events', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('admins')->nullOnDelete();
            }
            if (! Schema::hasColumn('security_events', 'review_notes')) {
                $table->text('review_notes')->nullable()->after('reviewed_by');
            }
        });

        if (! Schema::hasTable('evidence_audit_logs')) {
            Schema::create('evidence_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('security_evidence_id')->constrained('security_evidence')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->string('action', 50)->default('STREAM')->index();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['security_evidence_id', 'action']);
                $table->index(['user_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evidence_audit_logs');

        Schema::table('security_events', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['status', 'reviewed_at', 'reviewed_by', 'review_notes']);
        });
    }
};
