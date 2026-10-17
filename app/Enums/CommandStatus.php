<?php

namespace App\Enums;

enum CommandStatus: string
{
    case PENDING = 'PENDING';
    case SENT = 'SENT';
    case ACKNOWLEDGED = 'ACKNOWLEDGED';
    case EXECUTING = 'EXECUTING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case EXPIRED = 'EXPIRED';
    case UNSUPPORTED = 'UNSUPPORTED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
