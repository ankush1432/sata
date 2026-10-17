<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trusted_users', function (Blueprint $table) {
            if (! Schema::hasColumn('trusted_users', 'phone')) {
                $table->string('phone', 30)->nullable()->after('phone_number');
            }
            if (! Schema::hasColumn('trusted_users', 'verification_token')) {
                $table->string('verification_token', 64)->nullable()->index()->after('status');
            }
            if (! Schema::hasColumn('trusted_users', 'verification_reference')) {
                $table->string('verification_reference', 64)->nullable()->index()->after('verification_token');
            }
            if (! Schema::hasColumn('trusted_users', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->index()->after('verification_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trusted_users', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('trusted_users', 'phone')) {
                $cols[] = 'phone';
            }
            if (Schema::hasColumn('trusted_users', 'verification_token')) {
                $cols[] = 'verification_token';
            }
            if (Schema::hasColumn('trusted_users', 'verification_reference')) {
                $cols[] = 'verification_reference';
            }
            if (Schema::hasColumn('trusted_users', 'verified_at')) {
                $cols[] = 'verified_at';
            }
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
