<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Rules\CustomerEmailIsUnique;
use App\Services\CustomerService;

it('passes when the email is not taken', function (): void {
    $rule = new CustomerEmailIsUnique(resolve(CustomerService::class));

    $failed = false;
    $rule->validate('email', 'free@example.com', function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('fails when the value is not a string', function (): void {
    $rule = new CustomerEmailIsUnique(resolve(CustomerService::class));

    $failed = false;
    $rule->validate('email', ['not-a-string'], function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});

it('fails when the email is already taken', function (): void {
    $customer = resolve(CustomerService::class)->create(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: 'taken@example.com',
        bonusPercentage: 5,
    );

    $rule = new CustomerEmailIsUnique(resolve(CustomerService::class));

    $failed = false;
    $rule->validate('email', 'taken@example.com', function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeTrue()
        ->and($customer->email)->toBe('taken@example.com');
});

it('ignores the given customer id when checking for uniqueness', function (): void {
    $customer = resolve(CustomerService::class)->create(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: 'MT',
        email: 'self@example.com',
        bonusPercentage: 5,
    );

    $rule = new CustomerEmailIsUnique(resolve(CustomerService::class), $customer->id);

    $failed = false;
    $rule->validate('email', 'self@example.com', function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});
