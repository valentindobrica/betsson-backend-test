<?php

declare(strict_types=1);

use App\Actions\CreateCustomerAction;
use App\Enums\Gender;
use App\Services\DepositService;
use App\Services\WalletService;

it('is idempotent when the same deposit is confirmed by concurrent, duplicate webhook deliveries', function (): void {
    $customer = resolve(CreateCustomerAction::class)->execute(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: 'concurrent-webhook@example.com',
    );

    $amountCents = 10_000; // 100.00 EUR
    $deposit = resolve(DepositService::class)->create($customer, $amountCents);

    // Simulates a payment gateway delivering the same webhook call for the
    // same deposit multiple times, all landing at once.
    $deliveries = 10;
    $pids = [];

    for ($i = 0; $i < $deliveries; $i++) {
        $pids[] = forkAndRun(function () use ($deposit): void {
            resolve(DepositService::class)->approve($deposit->id);
        });
    }

    $failures = 0;

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);

        if (pcntl_wexitstatus($status) !== 0) {
            $failures++;
        }
    }

    // The forked children shared this process's MySQL socket; now that
    // they're done with it, this process needs its own connection again.
    reconnectDatabase();

    expect($failures)->toBe(0);

    $wallet = resolve(WalletService::class)->find($customer->id);

    // Credited exactly once no matter how many duplicate deliveries raced to
    // approve it. Without the row lock in DepositService::approve(), every
    // one of the 10 deliveries could see status Pending and credit the
    // wallet, landing on 10 * amountCents instead of amountCents.
    expect($wallet->balanceCents)->toBe($amountCents);

    $resolved = resolve(DepositService::class)->find($deposit->id);
    expect($resolved->depositNumber)->toBe(1);
});

it('serializes concurrent approvals of different deposits so the every-3rd-deposit bonus stays correct', function (): void {
    $customer = resolve(CreateCustomerAction::class)->execute(
        gender: Gender::Male,
        firstName: 'Jane',
        lastName: 'Roe',
        country: 'DE',
        email: 'concurrent-approvals@example.com',
    );

    $depositCount = 9;
    $amountCents = 10_000;

    $deposits = [];

    for ($i = 0; $i < $depositCount; $i++) {
        $deposits[] = resolve(DepositService::class)->create($customer, $amountCents);
    }

    $pids = [];

    foreach ($deposits as $deposit) {
        $pids[] = forkAndRun(function () use ($deposit): void {
            resolve(DepositService::class)->approve($deposit->id);
        });
    }

    $failures = 0;

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);

        if (pcntl_wexitstatus($status) !== 0) {
            $failures++;
        }
    }

    reconnectDatabase();

    expect($failures)->toBe(0);

    $wallet = resolve(WalletService::class)->find($customer->id);

    // 9 approvals of 100.00 each: 900.00, regardless of which order the OS
    // scheduled the 9 forked processes in.
    expect($wallet->balanceCents)->toBe($depositCount * $amountCents);

    // Exactly 3 of the 9 approvals are "every 3rd" (#3, #6, #9), no matter
    // the order they actually committed in, because the running approved
    // deposit count is read under the same wallet lock as the balance update.
    $bonusPerDeposit = intdiv($amountCents * $customer->bonusPercentage, 100);
    expect($wallet->bonusBalanceCents)->toBe(3 * $bonusPerDeposit);
});
