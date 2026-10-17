<?php

namespace App\Services;

use App\Enums\DeviceStatus;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Enums\SecurityEventType;
use App\Enums\TrustedUserStatus;
use App\Jobs\SendFcmNotificationJob;
use App\Models\Notification;
use App\Models\SecurityEvent;
use App\Models\TrustedUser;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService
{
    /**
     * Send notification to a specific device.
     */
    public function sendToDevice(
        UserDevice $device,
        NotificationType|string $type,
        string $title,
        string $body,
        array $payload = [],
        ?int $securityEventId = null,
        ?int $userId = null
    ): Notification {
        $typeString = $type instanceof NotificationType ? $type->value : (string) $type;
        $safePayload = NotificationPayloadSecurity::sanitizeDbPayload($payload);

        $notification = Notification::create([
            'user_id' => $userId ?? $device->user_id,
            'device_id' => $device->id,
            'security_event_id' => $securityEventId,
            'type' => $typeString,
            'title' => $title,
            'body' => $body,
            'payload' => $safePayload,
            'status' => NotificationStatus::PENDING->value,
            'sent_at' => null,
            'read_at' => null,
        ]);

        if (! empty($device->fcm_token)) {
            SendFcmNotificationJob::dispatch($notification, $device->fcm_token);
        } else {
            $notification->update([
                'status' => NotificationStatus::FAILED->value,
                'failure_reason' => 'Device has no FCM token registered',
            ]);
        }

        return $notification;
    }

    /**
     * Send notification to multiple devices.
     */
    public function sendToMultipleDevices(
        iterable $devices,
        NotificationType|string $type,
        string $title,
        string $body,
        array $payload = [],
        ?int $securityEventId = null
    ): Collection {
        $notifications = new Collection();

        foreach ($devices as $device) {
            if ($device instanceof UserDevice) {
                $notification = $this->sendToDevice(
                    $device,
                    $type,
                    $title,
                    $body,
                    $payload,
                    $securityEventId
                );
                $notifications->push($notification);
            }
        }

        return $notifications;
    }

    /**
     * Send notification to all active devices of a user.
     * If user has no active devices, record an in-app notification.
     */
    public function sendToUser(
        User $user,
        NotificationType|string $type,
        string $title,
        string $body,
        array $payload = [],
        ?int $securityEventId = null,
        ?int $deviceId = null
    ): Collection {
        $typeString = $type instanceof NotificationType ? $type->value : (string) $type;
        $safePayload = NotificationPayloadSecurity::sanitizeDbPayload($payload);

        // If specific device specified
        if ($deviceId) {
            $device = UserDevice::where('user_id', $user->id)->find($deviceId);
            if ($device) {
                $notification = $this->sendToDevice($device, $type, $title, $body, $safePayload, $securityEventId);

                return new Collection([$notification]);
            }
        }

        // Find active devices
        $devices = $user->devices()
            ->where('status', DeviceStatus::ACTIVE)
            ->whereNotNull('fcm_token')
            ->get();

        if ($devices->isNotEmpty()) {
            return $this->sendToMultipleDevices($devices, $type, $title, $body, $safePayload, $securityEventId);
        }

        // Check any devices with token
        $allDevices = $user->devices()->whereNotNull('fcm_token')->get();
        if ($allDevices->isNotEmpty()) {
            return $this->sendToMultipleDevices($allDevices, $type, $title, $body, $safePayload, $securityEventId);
        }

        // Fallback: No device token available, persist in DB for user
        $notification = Notification::create([
            'user_id' => $user->id,
            'device_id' => $deviceId ?? $user->current_device_id,
            'security_event_id' => $securityEventId,
            'type' => $typeString,
            'title' => $title,
            'body' => $body,
            'payload' => $safePayload,
            'status' => NotificationStatus::SENT->value,
            'sent_at' => now(),
            'read_at' => null,
        ]);

        return new Collection([$notification]);
    }

    /**
     * Notify for a Security Event:
     * 1. Main User
     * 2. Trusted User 1 (if verified)
     * 3. Trusted User 2 (if verified)
     */
    public function notifySecurityEvent(SecurityEvent $event, ?string $customMessage = null): array
    {
        $user = $event->user ?? User::find($event->user_id);
        if (! $user) {
            return [
                'delivered' => false,
                'message' => 'User not found for security event',
            ];
        }

        // Map security event to notification type
        $notificationType = $this->resolveNotificationTypeForEvent($event->event_type);

        $title = 'Security Alert: ' . ($event->event_type->value ?? (string) $event->event_type);
        $body = $customMessage ?? $event->description ?? "A security event ({$event->event_type->value}) was detected.";

        $basePayload = [
            'event_uuid' => $event->event_uuid,
            'event_type' => $event->event_type->value ?? (string) $event->event_type,
            'severity' => $event->severity ?? 'HIGH',
            'triggered_at' => $event->triggered_at?->toISOString() ?? now()->toISOString(),
            'device_id' => $event->device_id,
            'device_model' => $event->device?->device_model ?? $event->device?->model ?? 'Device',
            'has_evidence' => $event->evidence()->exists(),
        ];

        $notifications = [];

        // 1. Notify Main User
        try {
            $mainPayload = array_merge($basePayload, [
                'target_type' => 'MAIN_USER',
                'recipient_name' => $user->name,
                'recipient_role' => 'MAIN_USER',
                'user_id' => $user->id,
            ]);

            $mainDevice = $event->device ?? ($event->device_id ? UserDevice::find($event->device_id) : null);
            if ($mainDevice) {
                $mainNotif = $this->sendToDevice(
                    $mainDevice,
                    $notificationType,
                    $title,
                    $body,
                    $mainPayload,
                    $event->id,
                    $user->id
                );
            } else {
                $notifs = $this->sendToUser(
                    $user,
                    $notificationType,
                    $title,
                    $body,
                    $mainPayload,
                    $event->id
                );
                $mainNotif = $notifs->first();
            }

            if ($mainNotif) {
                $notifications[] = [
                    'target_type' => 'MAIN_USER',
                    'notification_id' => $mainNotif->id,
                    'status' => $mainNotif->status,
                ];
            }
        } catch (Throwable $e) {
            Log::error("Failed sending main user notification for event #{$event->id}: {$e->getMessage()}");
        }

        // 2 & 3. Notify Trusted User 1 & Trusted User 2 if verified
        $verifiedTrustedUsers = $user->trustedUsers()
            ->where('status', TrustedUserStatus::VERIFIED)
            ->where('is_active', true)
            ->whereNotNull('verified_at')
            ->orderBy('priority')
            ->take(2)
            ->get();

        foreach ($verifiedTrustedUsers as $index => $trustedContact) {
            $slot = 'TRUSTED_USER_' . ($index + 1);

            try {
                $trustedTitle = 'Emergency Alert for ' . ($user->name ?? 'User');
                $trustedBody = "Security alert: {$trustedContact->name}, an emergency event ({$event->event_type->value}) was detected on {$user->name}'s device.";

                $trustedPayload = array_merge($basePayload, [
                    'target_type' => $slot,
                    'recipient_role' => $slot,
                    'trusted_user_id' => $trustedContact->id,
                    'trusted_user_name' => $trustedContact->name,
                    'trusted_user_phone' => $trustedContact->phone,
                    'main_user_id' => $user->id,
                    'main_user_name' => $user->name,
                ]);

                // Create DB notification record
                $trustedNotif = Notification::create([
                    'user_id' => $user->id,
                    'device_id' => $event->device_id,
                    'security_event_id' => $event->id,
                    'title' => $trustedTitle,
                    'body' => $trustedBody,
                    'type' => 'SECURITY_ALERT_TRUSTED_CONTACT',
                    'payload' => NotificationPayloadSecurity::sanitizeDbPayload($trustedPayload),
                    'status' => NotificationStatus::PENDING->value,
                    'sent_at' => null,
                    'read_at' => null,
                ]);

                // Determine FCM token for trusted user
                $fcmToken = $trustedContact->fcm_token;
                if (empty($fcmToken)) {
                    // Check if trusted user phone is registered as an app user
                    $matchedUser = User::where('phone_number', $trustedContact->phone)->first();
                    if ($matchedUser) {
                        $fcmToken = $matchedUser->devices()
                            ->where('status', DeviceStatus::ACTIVE)
                            ->whereNotNull('fcm_token')
                            ->value('fcm_token');
                    }
                }

                if (! empty($fcmToken)) {
                    SendFcmNotificationJob::dispatch($trustedNotif, $fcmToken);
                } else {
                    // No token registered, mark delivered/sent for in-app alert or record
                    $trustedNotif->update([
                        'status' => NotificationStatus::DELIVERED->value,
                        'sent_at' => now(),
                    ]);
                }

                $notifications[] = [
                    'target_type' => $slot,
                    'notification_id' => $trustedNotif->id,
                    'status' => $trustedNotif->status,
                ];
            } catch (Throwable $e) {
                Log::error("Failed sending trusted user [{$slot}] notification for event #{$event->id}: {$e->getMessage()}");
            }
        }

        return [
            'delivered' => true,
            'event_id' => $event->id,
            'notifications_count' => count($notifications),
            'notifications' => $notifications,
        ];
    }

    /**
     * Map SecurityEventType to NotificationType.
     */
    protected function resolveNotificationTypeForEvent(SecurityEventType|string $eventType): NotificationType
    {
        $val = $eventType instanceof SecurityEventType ? $eventType->value : (string) $eventType;

        return match ($val) {
            SecurityEventType::INTRUDER_DETECTED->value, 'INTRUDER_DETECTED' => NotificationType::INTRUDER_DETECTED,
            SecurityEventType::SIM_CHANGED->value, 'SIM_CHANGED' => NotificationType::SIM_CHANGED,
            SecurityEventType::MOTION_DETECTED->value, 'MOTION_DETECTED' => NotificationType::MOTION_DETECTED,
            default => NotificationType::SECURITY_ALERT,
        };
    }
}
