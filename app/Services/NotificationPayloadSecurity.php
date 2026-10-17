<?php

namespace App\Services;

use App\Models\Notification;
use InvalidArgumentException;

class NotificationPayloadSecurity
{
    /**
     * Forbidden sensitive keywords that MUST NEVER appear in FCM payloads.
     */
    public const FORBIDDEN_KEYS = [
        'password',
        'otp',
        'auth_token',
        'token',
        'activation_code',
        'code',
        'master_pin',
        'pin',
        'private_photo_binary',
        'photo_binary',
        'binary',
        'private_credentials',
        'credentials',
        'secret',
        'private_key',
    ];

    /**
     * Build strict FCM references-only payload.
     * FCM data payload MUST contain references only:
     * - type
     * - event_id
     * - device_id
     * - notification_id
     */
    public static function buildFcmReferencesOnlyPayload(Notification $notification): array
    {
        return [
            'type' => (string) $notification->type,
            'event_id' => $notification->security_event_id ? (string) $notification->security_event_id : '',
            'device_id' => $notification->device_id ? (string) $notification->device_id : '',
            'notification_id' => (string) $notification->id,
        ];
    }

    /**
     * Sanitize DB payload by stripping sensitive keys.
     */
    public static function sanitizeDbPayload(?array $payload): array
    {
        if (empty($payload)) {
            return [];
        }

        $sanitized = [];
        foreach ($payload as $key => $value) {
            $lowerKey = strtolower((string) $key);

            // Strip forbidden fields
            if (self::isForbiddenKey($lowerKey)) {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeDbPayload($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Check if a given key matches any forbidden keyword.
     */
    public static function isForbiddenKey(string $key): bool
    {
        $normalized = str_replace(['_', '-'], '', strtolower($key));

        foreach (self::FORBIDDEN_KEYS as $forbidden) {
            $normalizedForbidden = str_replace(['_', '-'], '', strtolower($forbidden));
            if ($normalized === $normalizedForbidden || str_contains($normalized, $normalizedForbidden)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Assert that payload contains references only and NO sensitive data.
     *
     * @throws InvalidArgumentException
     */
    public static function assertFcmPayloadIsSafe(array $payload): void
    {
        $allowedKeys = ['type', 'event_id', 'device_id', 'notification_id'];

        foreach ($payload as $key => $value) {
            if (! in_array($key, $allowedKeys, true)) {
                throw new InvalidArgumentException("FCM payload must contain references only. Disallowed key: {$key}");
            }

            $lowerVal = is_string($value) ? strtolower($value) : '';
            foreach (self::FORBIDDEN_KEYS as $forbidden) {
                if (str_contains($lowerVal, $forbidden)) {
                    throw new InvalidArgumentException("Sensitive data detected in FCM payload value for key: {$key}");
                }
            }
        }
    }
}
