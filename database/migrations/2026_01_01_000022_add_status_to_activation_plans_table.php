<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activation_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('activation_plans', 'status')) {
                $table->string('status', 20)->default('ACTIVE')->index()->after('currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activation_plans', function (Blueprint $table) {
            if (Schema::hasColumn('activation_plans', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
