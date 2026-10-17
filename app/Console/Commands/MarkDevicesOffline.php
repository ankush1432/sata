<?php

namespace App\Console\Commands;

use App\Enums\DeviceStatus;
use App\Models\UserDevice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarkDevicesOffline extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devices:mark-offline {--hours= : Inactivity threshold in hours to mark device offline}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark devices as offline/inactive if their heartbeat/last seen timestamp exceeds the threshold, preserving device history.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hoursOption = $this->option('hours');
        $hours = $hoursOption !== null
            ? (int) $hoursOption
            : (int) config('subscription.device_offline_threshold_hours', config('devices.offline_threshold_hours', 24));

        $cutoffTime = now()->subHours($hours);
        $this->info("Scanning for active devices inactive for more than {$hours} hours (cutoff: {$cutoffTime->toIso8601String()})...");

        // Find ACTIVE devices whose last_seen_at <= cutoffTime, or where last_seen_at is NULL and created_at <= cutoffTime
        $staleDevices = UserDevice::where('status', DeviceStatus::ACTIVE)
            ->where(function ($query) use ($cutoffTime) {
                $query->where(function ($q) use ($cutoffTime) {
                    $q->whereNotNull('last_seen_at')
                      ->where('last_seen_at', '<=', $cutoffTime);
                })->orWhere(function ($q) use ($cutoffTime) {
                    $q->whereNull('last_seen_at')
                      ->where('created_at', '<=', $cutoffTime);
                });
            })
            ->get();

        $markedCount = 0;

        foreach ($staleDevices as $device) {
            DB::transaction(function () use ($device, &$markedCount) {
                $lockedDevice = UserDevice::lockForUpdate()->find($device->id);
                if (! $lockedDevice || $lockedDevice->status !== DeviceStatus::ACTIVE) {
                    return;
                }

                // Rule: Do NOT delete device history. Do NOT revoke. Mark status INACTIVE.
                $lockedDevice->update([
                    'status' => DeviceStatus::INACTIVE,
                ]);

                $markedCount++;
                Log::info("Device #{$lockedDevice->id} ({$lockedDevice->device_uuid}) marked as INACTIVE due to inactivity threshold.");
            });
        }

        $this->line(" - Devices marked offline (INACTIVE): {$markedCount}");
        $this->info('Integrity preserved: Device records, history, security events, and evidence remain fully intact.');
        $this->info('Device offline processing completed successfully.');

        return Command::SUCCESS;
    }
}
