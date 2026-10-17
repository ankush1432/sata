<?php

namespace App\Enums;

enum CommandType: string
{
    case LOCATE = 'LOCATE';
    case FACTORY_RESET = 'FACTORY_RESET';
    case KIOSK_LOCK = 'KIOSK_LOCK';
    case KIOSK_UNLOCK = 'KIOSK_UNLOCK';
    case WIPE_DATA = 'WIPE_DATA';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
