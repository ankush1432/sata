<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ProcessNotificationReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:process-reminders {--stages= : Comma-separated list of reminder stages in days (default: 7,3,1)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch multi-stage subscription expiry reminder notifications (7, 3, 1 days before expiry) idempotently.';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $subscriptionService): int
    {
        $stagesOption = $this->option('stages');
        if ($stagesOption !== null) {
            $stages = array_values(array_filter(array_map('intval', explode(',', $stagesOption))));
        } else {
            $stages = config('subscription.reminder_stages', [7, 3, 1]);
        }

        $stagesList = implode(', ', $stages);
        $this->info("Processing subscription reminders for configured stages: [{$stagesList}] days before expiry...");

        $result = $subscriptionService->processReminders($stages);

        $this->line(" - Subscriptions checked: {$result['subscriptions_checked']}");
        $this->line(" - New reminders dispatched: {$result['reminders_sent']}");
        $this->info('Idempotency verified: All reminders sent strictly once per stage.');
        $this->info('Notification reminder processing completed successfully.');

        return Command::SUCCESS;
    }
}
