<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Models\Customer;

function validCustomerRow(array $overrides = []): array
{
    return [
        'id' => '1',
        'gender' => 'female',
        'first_name' => 'Jane',
        'last_name' => 'Roe',
        'country' => 'DE',
        'email' => 'jane.roe@example.com',
        'bonus_percentage' => '12',
        'created_at' => '2026-01-01 10:00:00',
        'updated_at' => '2026-01-02 11:00:00',
        ...$overrides,
    ];
}

it('builds a customer from a database row', function (): void {
    $customer = Customer::fromDatabaseRow(validCustomerRow());

    expect($customer->id)->toBe(1)
        ->and($customer->gender)->toBe(Gender::Female)
        ->and($customer->firstName)->toBe('Jane')
        ->and($customer->lastName)->toBe('Roe')
        ->and($customer->country)->toBe('DE')
        ->and($customer->email)->toBe('jane.roe@example.com')
        ->and($customer->bonusPercentage)->toBe(12)
        ->and($customer->createdAt->toDateTimeString())->toBe('2026-01-01 10:00:00')
        ->and($customer->updatedAt->toDateTimeString())->toBe('2026-01-02 11:00:00');
});

it('throws when a numeric row value is not an int or a string', function (): void {
    Customer::fromDatabaseRow(validCustomerRow(['id' => ['not-scalar']]));
})->throws(UnexpectedValueException::class);

it('throws when a textual row value is not a string', function (): void {
    Customer::fromDatabaseRow(validCustomerRow(['first_name' => ['not-a-string']]));
})->throws(UnexpectedValueException::class);
