<?php

declare(strict_types=1);

namespace App\Concerns;

use Carbon\CarbonImmutable;
use UnexpectedValueException;

/**
 * Narrows the `mixed` values a PDO row fetch returns into the primitive types
 * our read-only data objects declare, used when building a DTO from a raw row.
 */
trait CastsDatabaseRowValues
{
    private static function toInt(mixed $value): int
    {
        if (is_int($value) || is_string($value)) {
            return (int) $value;
        }

        throw new UnexpectedValueException('Expected an int or string value from the database.');
    }

    private static function toNullableInt(mixed $value): ?int
    {
        return $value === null ? null : self::toInt($value);
    }

    private static function toString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        throw new UnexpectedValueException('Expected a string value from the database.');
    }

    private static function toNullableString(mixed $value): ?string
    {
        return $value === null ? null : self::toString($value);
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value) || is_int($value) || is_string($value)) {
            return (bool) $value;
        }

        throw new UnexpectedValueException('Expected a bool, int, or string value from the database.');
    }

    private static function toNullableDateTime(mixed $value): ?CarbonImmutable
    {
        $string = self::toNullableString($value);

        return $string === null ? null : CarbonImmutable::parse($string);
    }
}
