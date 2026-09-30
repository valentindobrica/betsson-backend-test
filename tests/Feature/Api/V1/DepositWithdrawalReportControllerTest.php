<?php

declare(strict_types=1);

use App\Actions\CreateCustomerAction;
use App\Enums\Gender;
use App\Models\Customer;
use App\Services\WithdrawalService;
use Carbon\CarbonImmutable;

function reportCustomerViaHttp(string $country = 'MT'): Customer
{
    return resolve(CreateCustomerAction::class)->execute(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: $country,
        email: fake()->unique()->safeEmail(),
    );
}

function approveDepositAtViaHttp(string $dateTime, Customer $customer, float $amount): void
{
    $previous = CarbonImmutable::now();
    CarbonImmutable::setTestNow(CarbonImmutable::parse($dateTime));

    try {
        $depositId = test()->postJson("/api/v1/customers/{$customer->id}/deposits", ['amount' => $amount])
            ->assertCreated()
            ->json('data.id');

        test()->postJson("/api/v1/deposits/{$depositId}/webhook", ['status' => 'approved'])
            ->assertOk();
    } finally {
        CarbonImmutable::setTestNow($previous);
    }
}

function approveWithdrawalAtViaHttp(string $dateTime, Customer $customer, float $amount): void
{
    $previous = CarbonImmutable::now();
    CarbonImmutable::setTestNow(CarbonImmutable::parse($dateTime));

    try {
        $withdrawalId = test()->postJson("/api/v1/customers/{$customer->id}/withdrawals", ['amount' => $amount])
            ->assertCreated()
            ->json('data.id');

        resolve(WithdrawalService::class)->markPendingAsInProgress();
        resolve(WithdrawalService::class)->approve($withdrawalId);
    } finally {
        CarbonImmutable::setTestNow($previous);
    }
}

it('defaults to the trailing 7 day window when no dates are given', function (): void {
    $customer = reportCustomerViaHttp();

    approveDepositAtViaHttp(CarbonImmutable::now()->toDateTimeString(), $customer, 100);

    $response = $this->getJson('/api/v1/reports/deposits-withdrawals');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.deposits_total', '100.00')
        ->assertJsonPath('data.0.unique_customers', 1);
});

it('filters by an explicit date range and reports negative withdrawal totals', function (): void {
    $customer = reportCustomerViaHttp('MT');

    approveDepositAtViaHttp('2026-01-05 10:00:00', $customer, 100);
    approveWithdrawalAtViaHttp('2026-01-05 11:00:00', $customer, 30);
    approveDepositAtViaHttp('2026-01-10 10:00:00', $customer, 999);

    $response = $this->getJson('/api/v1/reports/deposits-withdrawals?from=2026-01-05&to=2026-01-05');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.date', '2026-01-05')
        ->assertJsonPath('data.0.country', 'MT')
        ->assertJsonPath('data.0.deposits_total', '100.00')
        ->assertJsonPath('data.0.withdrawals_total', '-30.00');
});

it('returns an empty but successful response when nothing settled in range', function (): void {
    $response = $this->getJson('/api/v1/reports/deposits-withdrawals?from=2030-01-01&to=2030-01-07');

    $response->assertOk()->assertJsonCount(0, 'data');
});

it('rejects a to date before the from date', function (): void {
    $response = $this->getJson('/api/v1/reports/deposits-withdrawals?from=2026-01-10&to=2026-01-05');

    $response->assertStatus(422)->assertJsonValidationErrors(['to']);
});

it('rejects a badly formatted date', function (): void {
    $response = $this->getJson('/api/v1/reports/deposits-withdrawals?from=05-01-2026');

    $response->assertStatus(422)->assertJsonValidationErrors(['from']);
});

it('paginates the report', function (): void {
    $mt = reportCustomerViaHttp('MT');
    $de = reportCustomerViaHttp('DE');

    approveDepositAtViaHttp('2026-01-05 10:00:00', $mt, 10);
    approveDepositAtViaHttp('2026-01-05 10:00:00', $de, 10);

    $response = $this->getJson('/api/v1/reports/deposits-withdrawals?from=2026-01-05&to=2026-01-05&per_page=1&page=2');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 2);
});
