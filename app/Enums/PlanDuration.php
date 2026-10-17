<?php

namespace App\Enums;

enum PlanDuration: string
{
    case MONTHLY = 'MONTHLY';
    case QUARTERLY = 'QUARTERLY';
    case HALF_YEARLY = 'HALF_YEARLY';
    case YEARLY = 'YEARLY';

    public function days(): int
    {
        return match ($this) {
            self::MONTHLY => 30,
            self::QUARTERLY => 90,
            self::HALF_YEARLY => 180,
            self::YEARLY => 365,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromCode(?string $code): ?self
    {
        if (! $code) {
            return null;
        }

        return self::tryFrom(strtoupper(trim($code)));
    }

    public static function durationForCode(?string $code): ?int
    {
        return self::fromCode($code)?->days();
    }

    /**
     * @return array<string, int>
     */
    public static function durationsMap(): array
    {
        return [
            self::MONTHLY->value => self::MONTHLY->days(),
            self::QUARTERLY->value => self::QUARTERLY->days(),
            self::HALF_YEARLY->value => self::HALF_YEARLY->days(),
            self::YEARLY->value => self::YEARLY->days(),
        ];
    }
}
