<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\TrustedUser;
use App\Models\UserDevice;
use App\Services\FcmService;
use App\Services\NotificationPayloadSecurity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendFcmNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [5, 15, 30];

    public function __construct(
        public Notification $notification,
        public ?string $fcmToken
    ) {}

    public function handle(FcmService $fcmService): void
    {
        $notification = $this->notification->fresh() ?? $this->notification;

        // Skip if already sent or delivered
        if (in_array($notification->status, ['SENT', 'DELIVERED'], true)) {
            return;
        }

        if (empty($this->fcmToken)) {
            $notification->update([
                'status' => 'FAILED',
                'failure_reason' => 'Missing or empty FCM token',
            ]);

            return;
        }

        // Build references-only data payload (Never include sensitive data)
        $data = NotificationPayloadSecurity::buildFcmReferencesOnlyPayload($notification);

        // Attempt FCM dispatch
        $result = $fcmService->send(
            $this->fcmToken,
            $notification->title,
            $notification->body,
            $data
        );

        if ($result['success']) {
            $notification->update([
                'status' => 'SENT',
                'sent_at' => now(),
                'fcm_message_id' => $result['message_id'] ?? null,
                'failure_reason' => null,
            ]);

            return;
        }

        // Handle invalid/unregistered tokens
        if (! empty($result['is_unregistered'])) {
            $notification->update([
                'status' => 'FAILED',
                'failure_reason' => $result['message'] ?? 'FCM registration token is invalid or unregistered.',
            ]);

            // Clear invalid FCM token from devices and trusted contacts
            if ($notification->device_id) {
                UserDevice::where('id', $notification->device_id)->update(['fcm_token' => null]);
            }
            UserDevice::where('fcm_token', $this->fcmToken)->update(['fcm_token' => null]);
            TrustedUser::where('fcm_token', $this->fcmToken)->update(['fcm_token' => null]);

            $maskedToken = strlen($this->fcmToken) > 12
                ? substr($this->fcmToken, 0, 6) . '...' . substr($this->fcmToken, -6)
                : '***';
            Log::warning("Cleared invalid FCM token [{$maskedToken}] for notification #{$notification->id}");

            // Do NOT retry permanently invalid tokens
            return;
        }

        // Handle retryable/transient failures
        if (! empty($result['should_retry'])) {
            $attempts = $this->attempts();

            if ($attempts < $this->tries) {
                $notification->update([
                    'status' => 'PENDING',
                    'failure_reason' => "Attempt {$attempts} failed (transient): " . ($result['message'] ?? 'Retrying'),
                ]);

                $delay = $this->backoff[$attempts - 1] ?? 10;
                $this->release($delay);

                return;
            }

            // Exhausted retries
            $notification->update([
                'status' => 'FAILED',
                'failure_reason' => 'Exhausted retry attempts: ' . ($result['message'] ?? 'Transient failure'),
            ]);

            return;
        }

        // Fatal non-retryable error
        $notification->update([
            'status' => 'FAILED',
            'failure_reason' => $result['message'] ?? 'FCM dispatch failed',
        ]);
    }

    /**
     * Handle permanent job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $this->notification->update([
            'status' => 'FAILED',
            'failure_reason' => $exception?->getMessage() ?? 'Job permanently failed in queue',
        ]);
    }
}
