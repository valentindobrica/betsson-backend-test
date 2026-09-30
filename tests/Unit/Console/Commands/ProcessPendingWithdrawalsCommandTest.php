<?php

declare(strict_types=1);

use App\Actions\ApproveDepositAction;
use App\Actions\CreateCustomerAction;
use App\Actions\CreateDepositAction;
use App\Actions\CreateWithdrawalAction;
use App\Enums\Gender;
use App\Enums\WithdrawStatus;
use App\Services\WithdrawalService;

it('moves pending withdrawals to in-progress and processes them via the queued job', function (): void {
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

    $this->artisan('withdrawals:process-pending')->assertExitCode(0);

    $reloaded = resolve(WithdrawalService::class)->find($withdrawal->id);

    expect($reloaded->status)->toBe(WithdrawStatus::Approved)
        ->and($reloaded->preApproved)->toBeTrue();
});

it('reports zero withdrawals processed when none are pending', function (): void {
    $this->artisan('withdrawals:process-pending')
        ->expectsOutputToContain('0 withdrawal(s) moved to in-progress')
        ->assertExitCode(0);
});
