<?php

declare(strict_types=1);

use App\Actions\CreateCustomerAction;
use App\Enums\Gender;
use App\Models\Customer;

it('creates a customer with a random bonus percentage', function (): void {
    $customer = resolve(CreateCustomerAction::class)->execute(
        gender: Gender::Female,
        firstName: 'Jane',
        lastName: 'Roe',
        country: 'DE',
        email: 'jane.roe@example.com',
    );

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->gender)->toBe(Gender::Female)
        ->and($customer->firstName)->toBe('Jane')
        ->and($customer->lastName)->toBe('Roe')
        ->and($customer->country)->toBe('DE')
        ->and($customer->email)->toBe('jane.roe@example.com')
        ->and($customer->bonusPercentage)->toBeGreaterThanOrEqual(5)
        ->and($customer->bonusPercentage)->toBeLessThanOrEqual(20);
});
