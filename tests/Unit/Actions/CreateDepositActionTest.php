<?php

declare(strict_types=1);

use App\Actions\CreateCustomerAction;
use App\Actions\CreateDepositAction;
use App\Enums\DepositStatus;
use App\Enums\Gender;
use App\Models\Deposit;

it('creates a pending deposit for a customer', function (): void {
    $customer = resolve(CreateCustomerAction::class)->execute(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: fake()->unique()->safeEmail(),
    );

    $deposit = resolve(CreateDepositAction::class)->execute($customer, 5_000);

    expect($deposit)->toBeInstanceOf(Deposit::class)
        ->and($deposit->status)->toBe(DepositStatus::Pending)
        ->and($deposit->customerId)->toBe($customer->id)
        ->and($deposit->amountCents)->toBe(5_000);
});
