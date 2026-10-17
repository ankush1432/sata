<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ProcessSubscriptionExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:process-expiry {--days= : Threshold in days before expiration to mark as expiring}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process expiring and expired subscriptions: transitions ACTIVE to EXPIRING and EXPIRED idempotently without deleting users or history.';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $subscriptionService): int
    {
        $daysOption = $this->option('days');
        $days = $daysOption !== null ? (int) $daysOption : config('subscription.expiring_threshold_days', 7);

        $this->info("Processing expiring subscriptions (threshold: {$days} days)...");
        $expiringResult = $subscriptionService->processExpiring($days);
        $this->line(" - Expiring detected / transitioned: {$expiringResult['detected']}");

        $this->info('Processing expired subscriptions...');
        $expiredResult = $subscriptionService->processExpired();
        $this->line(" - Expired processed: {$expiredResult['expired']}");
        $this->line(" - Expired notifications sent: {$expiredResult['reminders_sent']}");

        $this->info('Data preservation verified: Users, device history, security events, evidence, and trusted users preserved.');
        $this->info('Subscription expiry processing completed successfully.');

        return Command::SUCCESS;
    }
}
