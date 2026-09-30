<?php

declare(strict_types=1);

use App\Actions\CreateCustomerAction;
use App\Enums\Gender;
use App\Models\Customer;
use App\Services\WalletService;

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

it('initializes a zero balance wallet for the new customer', function (): void {
    $customer = resolve(CreateCustomerAction::class)->execute(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: 'john.doe@example.com',
    );

    $wallet = resolve(WalletService::class)->find($customer->id);

    expect($wallet)->not->toBeNull()
        ->and($wallet->balanceCents)->toBe(0)
        ->and($wallet->bonusBalanceCents)->toBe(0);
});
