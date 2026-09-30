<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Converts monetary amounts between decimal strings (how they are stored in
 * the database and shown in the API) and integer cents (how the application
 * does arithmetic on them, to avoid floating point rounding errors).
 */
final class Money
{
    public static function toCents(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function toDecimalString(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
