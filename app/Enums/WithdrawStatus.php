<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a withdrawal. Backed values are the primary keys of the
 * `withdraw_statuses` lookup table, seeded from this enum's cases.
 */
enum WithdrawStatus: int
{
    /** Created, funds already reserved from the wallet; waiting for the scheduled command. */
    case Pending = 1;

    /** Picked up by the scheduled command, pre-approved, and queued for processing. */
    case InProgress = 2;

    /** Processed successfully by the queued job. */
    case Approved = 3;

    /** Processed by the queued job, but rejected. */
    case Disapproved = 4;

    /** Withdrawn by the customer, or cancelled by an operator, before processing. */
    case Cancelled = 5;

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Approved => 'Approved',
            self::Disapproved => 'Disapproved',
            self::Cancelled => 'Cancelled',
        };
    }
}
