<?php

declare(strict_types=1);

use App\Enums\WithdrawStatus;

it('has the expected backed cases and labels', function (): void {
    expect(WithdrawStatus::Pending->value)->toBe(1)
        ->and(WithdrawStatus::Pending->label())->toBe('Pending')
        ->and(WithdrawStatus::InProgress->value)->toBe(2)
        ->and(WithdrawStatus::InProgress->label())->toBe('In Progress')
        ->and(WithdrawStatus::Approved->value)->toBe(3)
        ->and(WithdrawStatus::Approved->label())->toBe('Approved')
        ->and(WithdrawStatus::Disapproved->value)->toBe(4)
        ->and(WithdrawStatus::Disapproved->label())->toBe('Disapproved')
        ->and(WithdrawStatus::Cancelled->value)->toBe(5)
        ->and(WithdrawStatus::Cancelled->label())->toBe('Cancelled');
});
