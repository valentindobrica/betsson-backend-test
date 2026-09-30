<?php

declare(strict_types=1);

use App\Enums\Gender;

it('has the expected backed cases', function (): void {
    expect(Gender::Male->value)->toBe('male')
        ->and(Gender::Female->value)->toBe('female')
        ->and(Gender::Other->value)->toBe('other');
});
