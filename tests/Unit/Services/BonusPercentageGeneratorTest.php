<?php

declare(strict_types=1);

use App\Services\BonusPercentageGenerator;

it('generates a percentage between 5 and 20', function (): void {
    $generator = new BonusPercentageGenerator();

    for ($i = 0; $i < 20; $i++) {
        expect($generator->generate())->toBeInt()
            ->toBeGreaterThanOrEqual(5)
            ->toBeLessThanOrEqual(20);
    }
});
