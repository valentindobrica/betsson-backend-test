<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\CastsDatabaseRowValues;
use App\Services\Money;

/**
 * One aggregated row of the deposit/withdrawal report: a single (date,
 * country) group. Not backed by a single table - assembled by
 * {@see \App\Services\DepositWithdrawalReportService} from a query joining
 * approved deposits and withdrawals against customers.
 */
final readonly class DepositWithdrawalReportRow
{
    use CastsDatabaseRowValues;

    public function __construct(
        public string $date,
        public string $country,
        public int $uniqueCustomers,
        public int $depositsCount,
        public int $depositsTotalCents,
        public int $withdrawalsCount,
        public int $withdrawalsTotalCents,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            date: self::toString($row['activity_date']),
            country: self::toString($row['country']),
            uniqueCustomers: self::toInt($row['unique_customers']),
            depositsCount: self::toInt($row['deposits_count']),
            depositsTotalCents: Money::toCents(self::toNumeric($row['deposits_total'])),
            withdrawalsCount: self::toInt($row['withdrawals_count']),
            withdrawalsTotalCents: Money::toCents(self::toNumeric($row['withdrawals_total'])),
        );
    }
}
