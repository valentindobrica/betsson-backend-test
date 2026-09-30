<?php

declare(strict_types=1);

use App\Models\DepositWithdrawalReportRow;

it('builds a report row from a database row', function (): void {
    $row = DepositWithdrawalReportRow::fromDatabaseRow([
        'activity_date' => '2026-01-05',
        'country' => 'MT',
        'unique_customers' => '3',
        'deposits_count' => '2',
        'deposits_total' => '300.00',
        'withdrawals_count' => '1',
        'withdrawals_total' => '30.00',
    ]);

    expect($row->date)->toBe('2026-01-05')
        ->and($row->country)->toBe('MT')
        ->and($row->uniqueCustomers)->toBe(3)
        ->and($row->depositsCount)->toBe(2)
        ->and($row->depositsTotalCents)->toBe(30000)
        ->and($row->withdrawalsCount)->toBe(1)
        ->and($row->withdrawalsTotalCents)->toBe(3000);
});

it('accepts numeric totals as returned by SQLite, not just decimal strings', function (): void {
    $row = DepositWithdrawalReportRow::fromDatabaseRow([
        'activity_date' => '2026-01-05',
        'country' => 'MT',
        'unique_customers' => 1,
        'deposits_count' => 1,
        'deposits_total' => 100.0,
        'withdrawals_count' => 0,
        'withdrawals_total' => 0,
    ]);

    expect($row->depositsTotalCents)->toBe(10000)
        ->and($row->withdrawalsTotalCents)->toBe(0);
});
