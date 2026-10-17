<?php

namespace App\Enums;

enum TrustedUserStatus: string
{
    case PENDING = 'PENDING';
    case VERIFIED = 'VERIFIED';
    case DISABLED = 'DISABLED';
    case REJECTED = 'REJECTED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
