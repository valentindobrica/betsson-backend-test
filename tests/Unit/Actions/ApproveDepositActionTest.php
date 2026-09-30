<?php

declare(strict_types=1);

use App\Actions\ApproveDepositAction;
use App\Actions\CreateCustomerAction;
use App\Actions\CreateDepositAction;
use App\Enums\DepositStatus;
use App\Enums\Gender;

it('approves a pending deposit', function (): void {
    $customer = resolve(CreateCustomerAction::class)->execute(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: fake()->unique()->safeEmail(),
    );

    $deposit = resolve(CreateDepositAction::class)->execute($customer, 5_000);

    $approved = resolve(ApproveDepositAction::class)->execute($deposit);

    expect($approved->id)->toBe($deposit->id)
        ->and($approved->status)->toBe(DepositStatus::Approved);
});
