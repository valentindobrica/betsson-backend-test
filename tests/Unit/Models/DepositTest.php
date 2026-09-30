<?php

declare(strict_types=1);

use App\Enums\DepositStatus;
use App\Models\Deposit;

function validDepositRow(array $overrides = []): array
{
    return [
        'id' => '1',
        'customer_id' => '2',
        'status_id' => '3',
        'amount' => '100.00',
        'bonus_amount' => '10.00',
        'deposit_number' => '3',
        'balance_after' => '300.00',
        'bonus_balance_after' => '10.00',
        'approved_at' => '2026-01-01 10:00:00',
        'created_at' => '2026-01-01 09:00:00',
        'updated_at' => '2026-01-01 10:00:00',
        ...$overrides,
    ];
}

it('builds an approved deposit from a database row', function (): void {
    $deposit = Deposit::fromDatabaseRow(validDepositRow());

    expect($deposit->id)->toBe(1)
        ->and($deposit->customerId)->toBe(2)
        ->and($deposit->status)->toBe(DepositStatus::Approved)
        ->and($deposit->amountCents)->toBe(10000)
        ->and($deposit->bonusAmountCents)->toBe(1000)
        ->and($deposit->depositNumber)->toBe(3)
        ->and($deposit->balanceAfterCents)->toBe(30000)
        ->and($deposit->bonusBalanceAfterCents)->toBe(1000)
        ->and($deposit->approvedAt?->toDateTimeString())->toBe('2026-01-01 10:00:00')
        ->and($deposit->createdAt->toDateTimeString())->toBe('2026-01-01 09:00:00')
        ->and($deposit->updatedAt->toDateTimeString())->toBe('2026-01-01 10:00:00');
});

it('builds a pending deposit with nullable fields left null', function (): void {
    $deposit = Deposit::fromDatabaseRow(validDepositRow([
        'status_id' => '1',
        'bonus_amount' => '0.00',
        'deposit_number' => null,
        'balance_after' => null,
        'bonus_balance_after' => null,
        'approved_at' => null,
    ]));

    expect($deposit->status)->toBe(DepositStatus::Pending)
        ->and($deposit->depositNumber)->toBeNull()
        ->and($deposit->balanceAfterCents)->toBeNull()
        ->and($deposit->bonusBalanceAfterCents)->toBeNull()
        ->and($deposit->approvedAt)->toBeNull();
});

it('throws when a row value has an unexpected type', function (): void {
    Deposit::fromDatabaseRow(validDepositRow(['amount' => ['not-a-string']]));
})->throws(UnexpectedValueException::class);
