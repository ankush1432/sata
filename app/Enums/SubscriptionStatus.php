<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case PENDING = 'PENDING';
    case ACTIVE = 'ACTIVE';
    case EXPIRING = 'EXPIRING';
    case EXPIRED = 'EXPIRED';
    case INACTIVE = 'INACTIVE';
    case DISABLED = 'DISABLED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
