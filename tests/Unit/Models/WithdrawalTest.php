<?php

declare(strict_types=1);

use App\Enums\WithdrawStatus;
use App\Models\Withdrawal;

function validWithdrawalRow(array $overrides = []): array
{
    return [
        'id' => '1',
        'customer_id' => '2',
        'status_id' => '3',
        'amount' => '40.00',
        'pre_approved' => '1',
        'balance_after' => '60.00',
        'processed_at' => '2026-01-01 10:00:00',
        'created_at' => '2026-01-01 09:00:00',
        'updated_at' => '2026-01-01 10:00:00',
        ...$overrides,
    ];
}

it('builds an approved withdrawal from a database row', function (): void {
    $withdrawal = Withdrawal::fromDatabaseRow(validWithdrawalRow());

    expect($withdrawal->id)->toBe(1)
        ->and($withdrawal->customerId)->toBe(2)
        ->and($withdrawal->status)->toBe(WithdrawStatus::Approved)
        ->and($withdrawal->amountCents)->toBe(4000)
        ->and($withdrawal->preApproved)->toBeTrue()
        ->and($withdrawal->balanceAfterCents)->toBe(6000)
        ->and($withdrawal->processedAt?->toDateTimeString())->toBe('2026-01-01 10:00:00')
        ->and($withdrawal->createdAt->toDateTimeString())->toBe('2026-01-01 09:00:00')
        ->and($withdrawal->updatedAt->toDateTimeString())->toBe('2026-01-01 10:00:00');
});

it('builds a pending withdrawal that has not been processed yet', function (): void {
    $withdrawal = Withdrawal::fromDatabaseRow(validWithdrawalRow([
        'status_id' => '1',
        'pre_approved' => '0',
        'processed_at' => null,
    ]));

    expect($withdrawal->status)->toBe(WithdrawStatus::Pending)
        ->and($withdrawal->preApproved)->toBeFalse()
        ->and($withdrawal->processedAt)->toBeNull();
});

it('throws when a row value has an unexpected type', function (): void {
    Withdrawal::fromDatabaseRow(validWithdrawalRow(['amount' => ['not-a-string']]));
})->throws(UnexpectedValueException::class);

it('throws when pre_approved has an unexpected type', function (): void {
    Withdrawal::fromDatabaseRow(validWithdrawalRow(['pre_approved' => ['not-a-bool']]));
})->throws(UnexpectedValueException::class);
