<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\CastsDatabaseRowValues;
use App\Services\Money;
use Carbon\CarbonImmutable;

/**
 * Plain data object representing a `wallets` row.
 *
 * Amounts are kept as integer cents internally to avoid floating point
 * rounding errors; see {@see Money} for the decimal string <-> cents conversion.
 */
final readonly class Wallet
{
    use CastsDatabaseRowValues;

    public function __construct(
        public int $customerId,
        public int $balanceCents,
        public int $bonusBalanceCents,
        public int $approvedDepositCount,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            customerId: self::toInt($row['customer_id']),
            balanceCents: Money::toCents(self::toString($row['balance'])),
            bonusBalanceCents: Money::toCents(self::toString($row['bonus_balance'])),
            approvedDepositCount: self::toInt($row['approved_deposit_count']),
            createdAt: CarbonImmutable::parse(self::toString($row['created_at'])),
            updatedAt: CarbonImmutable::parse(self::toString($row['updated_at'])),
        );
    }
}
