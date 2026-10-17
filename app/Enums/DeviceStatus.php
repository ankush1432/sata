<?php

namespace App\Enums;

enum DeviceStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case REVOKED = 'REVOKED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
