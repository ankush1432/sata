<?php

namespace App\Enums;

enum LiveLocationSessionStatus: string
{
    case ACTIVE = 'ACTIVE';
    case STOPPED = 'STOPPED';
    case EXPIRED = 'EXPIRED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
