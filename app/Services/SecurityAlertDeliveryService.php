<?php

namespace App\Services;

use App\Enums\TrustedUserStatus;
use App\Models\Notification;
use App\Models\SecurityEvent;
use App\Models\User;

class SecurityAlertDeliveryService
{
    /**
     * Deliver security alert to Main User, Trusted User 1, and Trusted User 2.
     *
     * Rules:
     * - Main user receives the alert.
     * - Maximum 2 trusted users.
     * - Verification required before receiving sensitive security alerts.
     * - Do not send private evidence directly in notification payload.
     *
     * @return array Summary of delivered alerts
     */
    public function deliverAlert(SecurityEvent $event, ?string $customMessage = null): array
    {
        $user = $event->user ?? User::find($event->user_id);
        if (! $user) {
            return [
                'delivered' => false,
                'message' => 'User not found for security event.',
            ];
        }

        $title = 'Security Alert: ' . ($event->event_type->value ?? (string) $event->event_type);
        $body = $customMessage ?? $event->description ?? "A security event ({$event->event_type->value}) was detected.";

        // Clean, sanitized payload WITHOUT private evidence files, URLs, or audio/photos
        $safePayload = [
            'event_uuid' => $event->event_uuid,
            'event_type' => $event->event_type->value ?? (string) $event->event_type,
            'severity' => $event->severity ?? 'HIGH',
            'triggered_at' => $event->triggered_at?->toISOString() ?? now()->toISOString(),
            'device_id' => $event->device_id,
            'device_model' => $event->device?->device_model ?? $event->device?->model ?? 'Unknown Device',
            // Private evidence is never embedded here
            'has_evidence' => $event->evidence()->exists(),
        ];

        $recipients = [];

        // 1. Target: Main User
        $mainDevice = $event->device ?? ($event->device_id ? \App\Models\UserDevice::find($event->device_id) : null);
        $mainNotification = Notification::create([
            'user_id' => $user->id,
            'device_id' => $event->device_id,
            'security_event_id' => $event->id,
            'title' => $title,
            'body' => $body,
            'type' => 'SECURITY_ALERT',
            'payload' => NotificationPayloadSecurity::sanitizeDbPayload(array_merge($safePayload, [
                'target_type' => 'MAIN_USER',
                'recipient_name' => $user->name,
                'user_id' => $user->id,
            ])),
            'status' => 'PENDING',
        ]);

        if (! empty($mainDevice?->fcm_token)) {
            \App\Jobs\SendFcmNotificationJob::dispatch($mainNotification, $mainDevice->fcm_token);
        } else {
            $mainNotification->update(['status' => 'DELIVERED', 'sent_at' => now()]);
        }

        $recipients[] = [
            'target_type' => 'MAIN_USER',
            'name' => $user->name,
            'user_id' => $user->id,
            'notification_id' => $mainNotification->id,
        ];

        // 2 & 3. Targets: Verified Trusted User 1 & Trusted User 2
        // Rule: Verification required before receiving sensitive security alerts
        $verifiedTrustedUsers = $user->trustedUsers()
            ->where('status', TrustedUserStatus::VERIFIED)
            ->where('is_active', true)
            ->whereNotNull('verified_at')
            ->orderBy('priority')
            ->take(2)
            ->get();

        foreach ($verifiedTrustedUsers as $index => $trustedContact) {
            $slot = 'TRUSTED_USER_' . ($index + 1);

            $trustedNotification = Notification::create([
                'user_id' => $user->id,
                'device_id' => $event->device_id,
                'security_event_id' => $event->id,
                'title' => 'Emergency Alert for ' . ($user->name ?? 'User'),
                'body' => "Security alert: {$trustedContact->name}, an emergency event ({$event->event_type->value}) was detected on {$user->name}'s device.",
                'type' => 'SECURITY_ALERT_TRUSTED_CONTACT',
                'payload' => NotificationPayloadSecurity::sanitizeDbPayload(array_merge($safePayload, [
                    'target_type' => $slot,
                    'trusted_user_id' => $trustedContact->id,
                    'trusted_user_name' => $trustedContact->name,
                    'trusted_user_phone' => $trustedContact->phone,
                    'main_user_id' => $user->id,
                    'main_user_name' => $user->name,
                ])),
                'status' => 'PENDING',
            ]);

            $trustedToken = $trustedContact->fcm_token;
            if (empty($trustedToken)) {
                $matchedUser = User::where('phone_number', $trustedContact->phone)->first();
                if ($matchedUser) {
                    $trustedToken = $matchedUser->devices()
                        ->where('status', \App\Enums\DeviceStatus::ACTIVE)
                        ->whereNotNull('fcm_token')
                        ->value('fcm_token');
                }
            }

            if (! empty($trustedToken)) {
                \App\Jobs\SendFcmNotificationJob::dispatch($trustedNotification, $trustedToken);
            } else {
                $trustedNotification->update(['status' => 'DELIVERED', 'sent_at' => now()]);
            }

            $recipients[] = [
                'target_type' => $slot,
                'name' => $trustedContact->name,
                'phone' => $trustedContact->phone,
                'trusted_user_id' => $trustedContact->id,
                'notification_id' => $trustedNotification->id,
            ];
        }

        return [
            'event_uuid' => $event->event_uuid,
            'recipients_count' => count($recipients),
            'recipients' => $recipients,
            'delivered_at' => now()->toISOString(),
        ];
    }
}
