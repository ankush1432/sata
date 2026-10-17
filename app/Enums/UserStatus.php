<?php

namespace App\Enums;

enum UserStatus: string
{
    case PENDING = 'PENDING';
    case OTP_VERIFIED = 'OTP_VERIFIED';
    case AWAITING_ACTIVATION = 'AWAITING_ACTIVATION';
    case ACTIVE = 'ACTIVE';
    case EXPIRED = 'EXPIRED';
    case DISABLED = 'DISABLED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
