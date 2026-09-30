<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Generates the random bonus percentage assigned to a customer on registration.
 */
final readonly class BonusPercentageGenerator
{
    private const int MIN_PERCENTAGE = 5;

    private const int MAX_PERCENTAGE = 20;

    public function generate(): int
    {
        return random_int(self::MIN_PERCENTAGE, self::MAX_PERCENTAGE);
    }
}
