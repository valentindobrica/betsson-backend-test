<?php

declare(strict_types=1);

use App\Actions\ApproveDepositAction;
use App\Actions\CreateDepositAction;
use App\Actions\CreateWithdrawalAction;
use App\Actions\DisapproveDepositAction;
use App\Enums\Gender;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Services\DepositWithdrawalReportService;
use App\Services\WalletService;
use Carbon\CarbonImmutable;

function reportService(): DepositWithdrawalReportService
{
    return resolve(DepositWithdrawalReportService::class);
}

function reportCustomer(string $country): Customer
{
    $customer = resolve(CustomerService::class)->create(
        gender: Gender::Male,
        firstName: 'John',
        lastName: 'Doe',
        country: $country,
        email: fake()->unique()->safeEmail(),
        bonusPercentage: 10,
    );

    resolve(WalletService::class)->initialize($customer->id);

    return $customer;
}

/**
 * Runs $callback with "now" pinned to $dateTime, so the row it creates
 * settles (approved_at/processed_at) at that exact instant, then restores
 * whatever "now" was before.
 */
function atInstant(string $dateTime, Closure $callback): void
{
    $previous = CarbonImmutable::now();

    CarbonImmutable::setTestNow(CarbonImmutable::parse($dateTime));

    try {
        $callback();
    } finally {
        CarbonImmutable::setTestNow($previous);
    }
}

function approvedDepositAt(string $dateTime, Customer $customer, int $amountCents): void
{
    atInstant($dateTime, function () use ($customer, $amountCents): void {
        $deposit = resolve(CreateDepositAction::class)->execute($customer, $amountCents);
        resolve(ApproveDepositAction::class)->execute($deposit);
    });
}

function approvedWithdrawalAt(string $dateTime, Customer $customer, int $amountCents): void
{
    atInstant($dateTime, function () use ($customer, $amountCents): void {
        $withdrawal = resolve(CreateWithdrawalAction::class)->execute($customer, $amountCents);
        resolve(App\Services\WithdrawalService::class)->markPendingAsInProgress();
        resolve(App\Services\WithdrawalService::class)->approve($withdrawal->id);
    });
}

it('groups approved deposits and withdrawals by settlement date and country', function (): void {
    $mt1 = reportCustomer('MT');
    $mt2 = reportCustomer('MT');
    $de1 = reportCustomer('DE');

    approvedDepositAt('2026-01-05 10:00:00', $mt1, 10_000);
    approvedDepositAt('2026-01-05 11:00:00', $mt2, 20_000);
    approvedDepositAt('2026-01-06 10:00:00', $de1, 5_000);
    approvedWithdrawalAt('2026-01-05 12:00:00', $mt1, 3_000);

    // Not approved: must not be counted anywhere.
    atInstant('2026-01-05 13:00:00', function () use ($mt1): void {
        resolve(CreateDepositAction::class)->execute($mt1, 99_999);
    });

    $page = reportService()->summarize(
        from: CarbonImmutable::parse('2026-01-05'),
        to: CarbonImmutable::parse('2026-01-05'),
        page: 1,
        perPage: 15,
    );

    expect($page->total())->toBe(1)
        ->and($page->items())->toHaveCount(1);

    $mtRow = $page->items()[0];

    expect($mtRow->date)->toBe('2026-01-05')
        ->and($mtRow->country)->toBe('MT')
        ->and($mtRow->uniqueCustomers)->toBe(2)
        ->and($mtRow->depositsCount)->toBe(2)
        ->and($mtRow->depositsTotalCents)->toBe(30_000)
        ->and($mtRow->withdrawalsCount)->toBe(1)
        ->and($mtRow->withdrawalsTotalCents)->toBe(3_000);
});

it('excludes a disapproved deposit entirely', function (): void {
    $customer = reportCustomer('MT');

    atInstant('2026-01-05 10:00:00', function () use ($customer): void {
        $deposit = resolve(CreateDepositAction::class)->execute($customer, 10_000);
        resolve(DisapproveDepositAction::class)->execute($deposit);
    });

    $page = reportService()->summarize(
        from: CarbonImmutable::parse('2026-01-05'),
        to: CarbonImmutable::parse('2026-01-05'),
        page: 1,
        perPage: 15,
    );

    expect($page->total())->toBe(0);
});

it('does not double count a customer who both deposited and withdrew on the same day', function (): void {
    $customer = reportCustomer('MT');

    approvedDepositAt('2026-01-05 10:00:00', $customer, 10_000);
    approvedWithdrawalAt('2026-01-05 11:00:00', $customer, 2_000);

    $page = reportService()->summarize(
        from: CarbonImmutable::parse('2026-01-05'),
        to: CarbonImmutable::parse('2026-01-05'),
        page: 1,
        perPage: 15,
    );

    expect($page->items()[0]->uniqueCustomers)->toBe(1);
});

it('includes the full day of "to" but excludes the day after', function (): void {
    $customer = reportCustomer('MT');

    approvedDepositAt('2026-01-05 23:59:59', $customer, 10_000);
    approvedDepositAt('2026-01-06 00:00:00', $customer, 20_000);

    $page = reportService()->summarize(
        from: CarbonImmutable::parse('2026-01-05'),
        to: CarbonImmutable::parse('2026-01-05'),
        page: 1,
        perPage: 15,
    );

    expect($page->total())->toBe(1)
        ->and($page->items()[0]->depositsCount)->toBe(1)
        ->and($page->items()[0]->depositsTotalCents)->toBe(10_000);
});

it('returns an empty page when nothing settled in the range', function (): void {
    $page = reportService()->summarize(
        from: CarbonImmutable::parse('2030-01-01'),
        to: CarbonImmutable::parse('2030-01-07'),
        page: 1,
        perPage: 15,
    );

    expect($page->total())->toBe(0)
        ->and($page->items())->toBe([]);
});

it('paginates groups', function (): void {
    $mt = reportCustomer('MT');
    $de = reportCustomer('DE');
    $fr = reportCustomer('FR');

    approvedDepositAt('2026-01-05 10:00:00', $mt, 1_000);
    approvedDepositAt('2026-01-05 10:00:00', $de, 1_000);
    approvedDepositAt('2026-01-05 10:00:00', $fr, 1_000);

    $firstPage = reportService()->summarize(
        from: CarbonImmutable::parse('2026-01-05'),
        to: CarbonImmutable::parse('2026-01-05'),
        page: 1,
        perPage: 2,
    );

    $secondPage = reportService()->summarize(
        from: CarbonImmutable::parse('2026-01-05'),
        to: CarbonImmutable::parse('2026-01-05'),
        page: 2,
        perPage: 2,
    );

    expect($firstPage->total())->toBe(3)
        ->and($firstPage->items())->toHaveCount(2)
        ->and($secondPage->items())->toHaveCount(1);
});
