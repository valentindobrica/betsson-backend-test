<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\CastsDatabaseRowValues;
use App\Enums\WithdrawStatus;
use App\Services\Money;
use Carbon\CarbonImmutable;

/**
 * Plain data object representing a `withdrawals` row.
 *
 * Unlike deposits, the amount is reserved from the wallet immediately on
 * creation (balanceAfterCents is always set), since the "never below 0"
 * rule must be enforced at request time, not at approval time.
 */
final readonly class Withdrawal
{
    use CastsDatabaseRowValues;

    public function __construct(
        public int $id,
        public int $customerId,
        public WithdrawStatus $status,
        public int $amountCents,
        public bool $preApproved,
        public int $balanceAfterCents,
        public ?CarbonImmutable $processedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            id: self::toInt($row['id']),
            customerId: self::toInt($row['customer_id']),
            status: WithdrawStatus::from(self::toInt($row['status_id'])),
            amountCents: Money::toCents(self::toNumeric($row['amount'])),
            preApproved: self::toBool($row['pre_approved']),
            balanceAfterCents: Money::toCents(self::toNumeric($row['balance_after'])),
            processedAt: self::toNullableDateTime($row['processed_at']),
            createdAt: CarbonImmutable::parse(self::toString($row['created_at'])),
            updatedAt: CarbonImmutable::parse(self::toString($row['updated_at'])),
        );
    }
}
