<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a deposit. Backed values are the primary keys of the
 * `deposit_statuses` lookup table, seeded from this enum's cases.
 */
enum DepositStatus: int
{
    /** Created, waiting for the payment gateway to confirm the outcome. */
    case Pending = 1;

    /** Reserved for a future asynchronous confirmation step; unused for now. */
    case InProgress = 2;

    /** Confirmed by the gateway; the wallet has been credited. */
    case Approved = 3;

    /** Confirmed by the gateway as failed; the wallet was never touched. */
    case Disapproved = 4;

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Approved => 'Approved',
            self::Disapproved => 'Disapproved',
        };
    }
}
