<?php

declare(strict_types=1);

use App\Services\Money;

it('converts a decimal amount to cents', function (): void {
    expect(Money::toCents('100.00'))->toBe(10000)
        ->and(Money::toCents(100))->toBe(10000)
        ->and(Money::toCents(99.99))->toBe(9999)
        ->and(Money::toCents('0.01'))->toBe(1)
        ->and(Money::toCents('10.1'))->toBe(1010);
});

it('converts cents back to a 2 decimal place string', function (): void {
    expect(Money::toDecimalString(10000))->toBe('100.00')
        ->and(Money::toDecimalString(9999))->toBe('99.99')
        ->and(Money::toDecimalString(1))->toBe('0.01')
        ->and(Money::toDecimalString(0))->toBe('0.00');
});
