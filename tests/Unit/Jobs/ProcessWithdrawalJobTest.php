<?php

declare(strict_types=1);

use App\Actions\ApproveDepositAction;
use App\Actions\CreateCustomerAction;
use App\Actions\CreateDepositAction;
use App\Actions\CreateWithdrawalAction;
use App\Enums\Gender;
use App\Enums\WithdrawStatus;
use App\Jobs\ProcessWithdrawalJob;
use App\Services\WithdrawalService;

function createInProgressWithdrawal(): int
{
    $customer = resolve(CreateCustomerAction::class)->execute(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: fake()->unique()->safeEmail(),
    );

    $deposit = resolve(CreateDepositAction::class)->execute($customer, 10_000);
    resolve(ApproveDepositAction::class)->execute($deposit);

    $withdrawal = resolve(CreateWithdrawalAction::class)->execute($customer, 1_000);
    resolve(WithdrawalService::class)->markPendingAsInProgress();

    return $withdrawal->id;
}

it('has a unique id scoped to the withdrawal', function (): void {
    $job = new ProcessWithdrawalJob(42);

    expect($job->uniqueId())->toBe('withdrawal-42')
        ->and($job->withdrawalId)->toBe(42);
});

it('approves the withdrawal it was dispatched for', function (): void {
    $withdrawalId = createInProgressWithdrawal();

    new ProcessWithdrawalJob($withdrawalId)->handle(resolve(WithdrawalService::class));

    $withdrawal = resolve(WithdrawalService::class)->find($withdrawalId);

    expect($withdrawal->status)->toBe(WithdrawStatus::Approved);
});
