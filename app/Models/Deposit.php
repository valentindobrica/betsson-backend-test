<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\CastsDatabaseRowValues;
use App\Enums\DepositStatus;
use App\Services\Money;
use Carbon\CarbonImmutable;

/**
 * Plain data object representing a `deposits` row.
 *
 * `bonusAmountCents`, `depositNumber`, `balanceAfterCents` and
 * `bonusBalanceAfterCents` are only populated once the deposit is approved;
 * they stay 0/null while it is pending, and null forever if disapproved.
 */
final readonly class Deposit
{
    use CastsDatabaseRowValues;

    public function __construct(
        public int $id,
        public int $customerId,
        public DepositStatus $status,
        public int $amountCents,
        public int $bonusAmountCents,
        public ?int $depositNumber,
        public ?int $balanceAfterCents,
        public ?int $bonusBalanceAfterCents,
        public ?CarbonImmutable $approvedAt,
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
            status: DepositStatus::from(self::toInt($row['status_id'])),
            amountCents: Money::toCents(self::toString($row['amount'])),
            bonusAmountCents: Money::toCents(self::toString($row['bonus_amount'])),
            depositNumber: self::toNullableInt($row['deposit_number']),
            balanceAfterCents: self::toNullableCents($row['balance_after']),
            bonusBalanceAfterCents: self::toNullableCents($row['bonus_balance_after']),
            approvedAt: self::toNullableDateTime($row['approved_at']),
            createdAt: CarbonImmutable::parse(self::toString($row['created_at'])),
            updatedAt: CarbonImmutable::parse(self::toString($row['updated_at'])),
        );
    }

    private static function toNullableCents(mixed $value): ?int
    {
        return $value === null ? null : Money::toCents(self::toString($value));
    }
}
