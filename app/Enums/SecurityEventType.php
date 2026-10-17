<?php

namespace App\Enums;

enum SecurityEventType: string
{
    case INTRUDER_DETECTED = 'INTRUDER_DETECTED';
    case INTRUDER_PHOTO = 'INTRUDER_PHOTO';
    case WRONG_PIN_ATTEMPTS = 'WRONG_PIN_ATTEMPTS';
    case SIM_CHANGED = 'SIM_CHANGED';
    case MOTION_DETECTED = 'MOTION_DETECTED';
    case PROXIMITY_ALERT = 'PROXIMITY_ALERT';
    case REMOTE_COMMAND = 'REMOTE_COMMAND';
    case PROTECTION_ENABLED = 'PROTECTION_ENABLED';
    case PROTECTION_DISABLED = 'PROTECTION_DISABLED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
