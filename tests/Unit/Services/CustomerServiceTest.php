<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Validation\ValidationException;

function customerService(): CustomerService
{
    return resolve(CustomerService::class);
}

it('creates and finds a customer', function (): void {
    $customer = customerService()->create(
        gender: Gender::Female,
        firstName: 'Jane',
        lastName: 'Roe',
        country: 'DE',
        email: 'jane.roe@example.com',
        bonusPercentage: 10,
    );

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($customer->gender)->toBe(Gender::Female)
        ->and($customer->firstName)->toBe('Jane')
        ->and($customer->lastName)->toBe('Roe')
        ->and($customer->country)->toBe('DE')
        ->and($customer->email)->toBe('jane.roe@example.com')
        ->and($customer->bonusPercentage)->toBe(10);

    expect(customerService()->find($customer->id))
        ->toBeInstanceOf(Customer::class)
        ->and(customerService()->find($customer->id)?->email)->toBe('jane.roe@example.com');
});

it('returns null when finding a missing customer', function (): void {
    expect(customerService()->find(999999))->toBeNull();
});

it('updates a customer, leaving its bonus percentage untouched', function (): void {
    $customer = customerService()->create(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: 'john.doe@example.com',
        bonusPercentage: 18,
    );

    $updated = customerService()->update(
        customer: $customer,
        gender: Gender::Other,
        firstName: 'Johnny',
        lastName: 'Doe',
        country: 'GB',
        email: 'johnny@example.com',
    );

    expect($updated->gender)->toBe(Gender::Other)
        ->and($updated->firstName)->toBe('Johnny')
        ->and($updated->country)->toBe('GB')
        ->and($updated->email)->toBe('johnny@example.com')
        ->and($updated->bonusPercentage)->toBe(18);
});

it('turns a duplicate email clash on create into a validation exception', function (): void {
    customerService()->create(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: 'race@example.com',
        bonusPercentage: 5,
    );

    // Bypasses the CustomerEmailIsUnique pre-check to simulate two concurrent
    // requests both passing validation before either has committed its insert.
    expect(fn (): Customer => customerService()->create(
        gender: Gender::Female,
        firstName: 'Jane',
        lastName: 'Roe',
        country: 'DE',
        email: 'race@example.com',
        bonusPercentage: 10,
    ))->toThrow(ValidationException::class);
});

it('turns a duplicate email clash on update into a validation exception', function (): void {
    customerService()->create(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: 'first@example.com',
        bonusPercentage: 5,
    );

    $second = customerService()->create(
        gender: Gender::Female,
        firstName: 'Jane',
        lastName: 'Roe',
        country: 'DE',
        email: 'second@example.com',
        bonusPercentage: 10,
    );

    expect(fn (): Customer => customerService()->update(
        customer: $second,
        gender: $second->gender,
        firstName: $second->firstName,
        lastName: $second->lastName,
        country: $second->country,
        email: 'first@example.com',
    ))->toThrow(ValidationException::class);
});

it('rethrows database errors unrelated to email uniqueness', function (): void {
    expect(fn (): Customer => customerService()->create(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: str_repeat('a', 250).'@example.com',
        bonusPercentage: 5,
    ))->toThrow(PDOException::class);
});

it('reports whether an email already exists', function (): void {
    $customer = customerService()->create(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: 'john.doe@example.com',
        bonusPercentage: 5,
    );

    expect(customerService()->emailExists('john.doe@example.com'))->toBeTrue()
        ->and(customerService()->emailExists('missing@example.com'))->toBeFalse()
        ->and(customerService()->emailExists('john.doe@example.com', $customer->id))->toBeFalse()
        ->and(customerService()->emailExists('john.doe@example.com', $customer->id + 1))->toBeTrue();
});
