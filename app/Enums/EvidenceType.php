<?php

namespace App\Enums;

enum EvidenceType: string
{
    case FRONT_PHOTO = 'FRONT_PHOTO';
    case BACK_PHOTO = 'BACK_PHOTO';
    case FRONT_CAMERA = 'FRONT_CAMERA';
    case BACK_CAMERA = 'BACK_CAMERA';
    case AUDIO_CLIP = 'AUDIO_CLIP';
    case SCREENSHOT = 'SCREENSHOT';
    case SYSTEM_LOG = 'SYSTEM_LOG';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
