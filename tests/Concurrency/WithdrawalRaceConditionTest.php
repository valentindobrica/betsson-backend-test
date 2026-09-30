<?php

declare(strict_types=1);

use App\Actions\CreateCustomerAction;
use App\Actions\CreateWithdrawalAction;
use App\Enums\Gender;
use App\Services\DepositService;
use App\Services\WalletService;

it('serializes concurrent withdrawals so the balance never goes negative', function (): void {
    $customer = resolve(CreateCustomerAction::class)->execute(
        gender: Gender::Male,
        firstName: 'Jane',
        lastName: 'Roe',
        country: 'DE',
        email: 'concurrent-withdrawals@example.com',
    );

    // Exactly enough real balance for 5 of the 10 concurrent withdrawal
    // attempts below to succeed.
    $deposit = resolve(DepositService::class)->create($customer, 50_000);
    resolve(DepositService::class)->approve($deposit->id);

    $attempts = 10;
    $amountCents = 10_000; // 100.00 EUR per withdrawal attempt

    $pids = [];

    for ($i = 0; $i < $attempts; $i++) {
        $pids[] = forkAndRun(function () use ($customer, $amountCents): void {
            resolve(CreateWithdrawalAction::class)->execute($customer, $amountCents);
        });
    }

    $succeeded = 0;

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);

        if (pcntl_wexitstatus($status) === 0) {
            $succeeded++;
        }
    }

    // The forked children shared this process's MySQL socket; now that
    // they're done with it, this process needs its own connection again.
    reconnectDatabase();

    // Without the row lock, two concurrent withdrawals could both read the
    // same "sufficient" balance and both succeed, taking the balance below
    // 0. With it, only as many withdrawals succeed as the balance allows.
    expect($succeeded)->toBe(5);

    $wallet = resolve(WalletService::class)->find($customer->id);

    expect($wallet->balanceCents)->toBe(0);
});
