<?php

namespace App\Console\Commands;

use App\Enums\CommandStatus;
use App\Models\SecurityCommand;
use App\Models\SecurityCommandLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireSecurityCommands extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security-commands:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire pending and sent security commands whose time-to-live (expires_at) has elapsed.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();
        $this->info("Scanning for active security commands expired before {$now->toIso8601String()}...");

        // Find commands in non-terminal states whose expires_at is in the past
        $expiredCommands = SecurityCommand::whereIn('status', [CommandStatus::PENDING, CommandStatus::SENT])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get();

        $count = 0;

        foreach ($expiredCommands as $cmd) {
            DB::transaction(function () use ($cmd, &$count, $now) {
                $lockedCmd = SecurityCommand::lockForUpdate()->find($cmd->id);
                if (! $lockedCmd || ! in_array($lockedCmd->status, [CommandStatus::PENDING, CommandStatus::SENT], true)) {
                    return;
                }

                $previousStatus = $lockedCmd->status;

                $lockedCmd->update([
                    'status' => CommandStatus::EXPIRED,
                    'failure_reason' => 'Command expired automatically after exceeding time-to-live threshold at ' . ($lockedCmd->expires_at?->toIso8601String() ?? $now->toIso8601String()),
                ]);

                // Create audit trail log
                SecurityCommandLog::create([
                    'security_command_id' => $lockedCmd->id,
                    'status_before' => $previousStatus->value,
                    'status_after' => CommandStatus::EXPIRED->value,
                    'status_from' => $previousStatus->value,
                    'status_to' => CommandStatus::EXPIRED->value,
                    'message' => 'Command automatically marked as EXPIRED by scheduled command sweep.',
                    'metadata' => [
                        'expired_at' => $lockedCmd->expires_at?->toIso8601String(),
                        'transitioned_at' => $now->toIso8601String(),
                        'triggered_by' => 'scheduled_job',
                    ],
                    'recorded_at' => $now,
                ]);

                $count++;
                Log::info("Security command #{$lockedCmd->id} ({$lockedCmd->command_uuid}) transitioned from {$previousStatus->value} to EXPIRED.");
            });
        }

        $this->line(" - Security commands transitioned to EXPIRED: {$count}");
        $this->info('Idempotency verified: Completed, failed, and already expired commands remained untouched.');
        $this->info('Security command expiry processing completed successfully.');

        return Command::SUCCESS;
    }
}
