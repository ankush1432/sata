<?php

namespace App\Enums;

enum ActivationCodeStatus: string
{
    case AVAILABLE = 'AVAILABLE';
    case ACTIVATED = 'ACTIVATED';
    case EXPIRED = 'EXPIRED';
    case DISABLED = 'DISABLED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
