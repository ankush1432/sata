<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activation_codes', function (Blueprint $table) {
            $table->string('serial_number', 64)->nullable()->unique()->after('id');
            $table->string('code_hash', 64)->nullable()->unique()->after('serial_number');
            $table->string('code_last4', 10)->nullable()->index()->after('code_hash');
            $table->boolean('is_active')->default(true)->after('is_redeemed');
            $table->foreignId('generated_by_admin_id')->nullable()->after('created_by_admin_id')->constrained('admins')->nullOnDelete();
            $table->foreignId('activated_by_user_id')->nullable()->after('redeemed_by_user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('activated_device_id')->nullable()->after('activated_by_user_id')->constrained('user_devices')->nullOnDelete();
            $table->timestamp('activated_at')->nullable()->after('redeemed_at');
        });

        // Backfill existing rows if any
        $rows = DB::table('activation_codes')->get();
        foreach ($rows as $row) {
            $serialNumber = sprintf('SAT-%06d', $row->id);
            $rawCode = $row->code ?? $serialNumber;
            $codeHash = hash('sha256', $rawCode);
            $codeLast4 = substr($rawCode, -4);
            $status = 'AVAILABLE';
            if ($row->is_redeemed) {
                $status = 'ACTIVATED';
            } elseif ($row->status === 'DISABLED') {
                $status = 'DISABLED';
            } elseif ($row->expires_at && \Carbon\Carbon::parse($row->expires_at)->isPast()) {
                $status = 'EXPIRED';
            }

            DB::table('activation_codes')->where('id', $row->id)->update([
                'serial_number' => $serialNumber,
                'code_hash' => $codeHash,
                'code_last4' => $codeLast4,
                'status' => $status,
                'generated_by_admin_id' => $row->created_by_admin_id,
                'activated_by_user_id' => $row->redeemed_by_user_id,
                'activated_at' => $row->redeemed_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('activation_codes', function (Blueprint $table) {
            $table->dropForeign(['generated_by_admin_id']);
            $table->dropForeign(['activated_by_user_id']);
            $table->dropForeign(['activated_device_id']);
            $table->dropColumn([
                'serial_number',
                'code_hash',
                'code_last4',
                'generated_by_admin_id',
                'activated_by_user_id',
                'activated_device_id',
                'activated_at',
            ]);
        });
    }
};
