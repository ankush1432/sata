<?php

namespace App\Enums;

enum NotificationType: string
{
    case SUBSCRIPTION_ACTIVATED = 'SUBSCRIPTION_ACTIVATED';
    case SUBSCRIPTION_EXPIRING = 'SUBSCRIPTION_EXPIRING';
    case SUBSCRIPTION_EXPIRED = 'SUBSCRIPTION_EXPIRED';
    case INTRUDER_DETECTED = 'INTRUDER_DETECTED';
    case SIM_CHANGED = 'SIM_CHANGED';
    case MOTION_DETECTED = 'MOTION_DETECTED';
    case REMOTE_COMMAND = 'REMOTE_COMMAND';
    case SECURITY_ALERT = 'SECURITY_ALERT';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function tryFromValue(?string $value): ?self
    {
        if (! $value) {
            return null;
        }

        // Handle case-insensitive or legacy variations
        $normalized = strtoupper(trim($value));

        return self::tryFrom($normalized);
    }
}
