<?php

declare(strict_types=1);

use App\Actions\ApproveDepositAction;
use App\Actions\CreateCustomerAction;
use App\Actions\CreateDepositAction;
use App\Actions\CreateWithdrawalAction;
use App\Enums\Gender;
use App\Enums\WithdrawStatus;
use App\Models\Withdrawal;

it('creates a withdrawal for a customer', function (): void {
    $customer = resolve(CreateCustomerAction::class)->execute(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: fake()->unique()->safeEmail(),
    );

    $deposit = resolve(CreateDepositAction::class)->execute($customer, 5_000);
    resolve(ApproveDepositAction::class)->execute($deposit);

    $withdrawal = resolve(CreateWithdrawalAction::class)->execute($customer, 2_000);

    expect($withdrawal)->toBeInstanceOf(Withdrawal::class)
        ->and($withdrawal->status)->toBe(WithdrawStatus::Pending)
        ->and($withdrawal->customerId)->toBe($customer->id)
        ->and($withdrawal->amountCents)->toBe(2_000)
        ->and($withdrawal->balanceAfterCents)->toBe(3_000);
});
