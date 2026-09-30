<?php

declare(strict_types=1);

use App\Actions\CreateCustomerAction;
use App\Actions\UpdateCustomerAction;
use App\Enums\Gender;

it('updates a customer, leaving its bonus percentage untouched', function (): void {
    $customer = resolve(CreateCustomerAction::class)->execute(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: 'john.doe@example.com',
    );

    $updated = resolve(UpdateCustomerAction::class)->execute(
        customer: $customer,
        gender: Gender::Other,
        firstName: 'Johnny',
        lastName: 'Doe',
        country: 'GB',
        email: 'johnny@example.com',
    );

    expect($updated->id)->toBe($customer->id)
        ->and($updated->gender)->toBe(Gender::Other)
        ->and($updated->firstName)->toBe('Johnny')
        ->and($updated->country)->toBe('GB')
        ->and($updated->email)->toBe('johnny@example.com')
        ->and($updated->bonusPercentage)->toBe($customer->bonusPercentage);
});
