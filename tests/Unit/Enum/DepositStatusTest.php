<?php

declare(strict_types=1);

use App\Enums\DepositStatus;

it('has the expected backed cases and labels', function (): void {
    expect(DepositStatus::Pending->value)->toBe(1)
        ->and(DepositStatus::Pending->label())->toBe('Pending')
        ->and(DepositStatus::InProgress->value)->toBe(2)
        ->and(DepositStatus::InProgress->label())->toBe('In Progress')
        ->and(DepositStatus::Approved->value)->toBe(3)
        ->and(DepositStatus::Approved->label())->toBe('Approved')
        ->and(DepositStatus::Disapproved->value)->toBe(4)
        ->and(DepositStatus::Disapproved->label())->toBe('Disapproved');
});
