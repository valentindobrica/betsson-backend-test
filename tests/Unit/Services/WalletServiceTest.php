<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Models\Wallet;
use App\Services\CustomerService;
use App\Services\WalletService;

function walletService(): WalletService
{
    return resolve(WalletService::class);
}

function createBareCustomerId(): int
{
    return resolve(CustomerService::class)->create(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: fake()->unique()->safeEmail(),
        bonusPercentage: 10,
    )->id;
}

it('initializes a zero balance wallet for a customer', function (): void {
    $customerId = createBareCustomerId();

    $wallet = walletService()->initialize($customerId);

    expect($wallet)->toBeInstanceOf(Wallet::class)
        ->and($wallet->customerId)->toBe($customerId)
        ->and($wallet->balanceCents)->toBe(0)
        ->and($wallet->bonusBalanceCents)->toBe(0)
        ->and($wallet->approvedDepositCount)->toBe(0);
});

it('finds a customer wallet', function (): void {
    $customerId = createBareCustomerId();
    walletService()->initialize($customerId);

    $wallet = walletService()->find($customerId);

    expect($wallet)->toBeInstanceOf(Wallet::class)
        ->and($wallet->customerId)->toBe($customerId);
});

it('returns null when finding a missing wallet', function (): void {
    expect(walletService()->find(999999))->toBeNull();
});

it('locks and returns the wallet for update', function (): void {
    $customerId = createBareCustomerId();
    walletService()->initialize($customerId);

    $wallet = walletService()->lockForUpdate($customerId);

    expect($wallet)->toBeInstanceOf(Wallet::class)
        ->and($wallet->customerId)->toBe($customerId);
});

it('throws when locking a wallet that does not exist', function (): void {
    expect(fn (): Wallet => walletService()->lockForUpdate(999999))->toThrow(RuntimeException::class);
});

it('debits the real balance without touching the bonus balance', function (): void {
    $customerId = createBareCustomerId();
    walletService()->initialize($customerId);
    walletService()->creditDeposit($customerId, 10_000, 500, 3);

    walletService()->debitBalance($customerId, 6_000);

    $wallet = walletService()->find($customerId);
    expect($wallet->balanceCents)->toBe(6_000)
        ->and($wallet->bonusBalanceCents)->toBe(500)
        ->and($wallet->approvedDepositCount)->toBe(3);
});

it('credits a deposit, updating balances and the approved deposit count together', function (): void {
    $customerId = createBareCustomerId();
    walletService()->initialize($customerId);

    walletService()->creditDeposit($customerId, 10_000, 1_000, 3);

    $wallet = walletService()->find($customerId);
    expect($wallet->balanceCents)->toBe(10_000)
        ->and($wallet->bonusBalanceCents)->toBe(1_000)
        ->and($wallet->approvedDepositCount)->toBe(3);
});
