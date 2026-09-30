<?php

declare(strict_types=1);

use App\Actions\CreateCustomerAction;
use App\Actions\CreateDepositAction;
use App\Actions\DisapproveDepositAction;
use App\Enums\DepositStatus;
use App\Enums\Gender;

it('disapproves a pending deposit', function (): void {
    $customer = resolve(CreateCustomerAction::class)->execute(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: fake()->unique()->safeEmail(),
    );

    $deposit = resolve(CreateDepositAction::class)->execute($customer, 5_000);

    $disapproved = resolve(DisapproveDepositAction::class)->execute($deposit);

    expect($disapproved->id)->toBe($deposit->id)
        ->and($disapproved->status)->toBe(DepositStatus::Disapproved);
});
