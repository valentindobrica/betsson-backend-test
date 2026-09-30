<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\WithdrawalService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;

/**
 * Finalizes one pre-approved, in-progress withdrawal, dispatched once per
 * withdrawal by {@see \App\Console\Commands\ProcessPendingWithdrawalsCommand}.
 *
 * ShouldBeUnique stops the same withdrawal id being queued twice at once;
 * {@see WithdrawalService::approve()} is also idempotent on its own (locks
 * the row and checks its status/pre_approved flag), as a second line of
 * defense in case a job is ever retried or the unique lock expires.
 */
#[Backoff(60)]
#[Tries(3)]
#[UniqueFor(3600)]
final class ProcessWithdrawalJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $withdrawalId) {}

    public function uniqueId(): string
    {
        return 'withdrawal-'.$this->withdrawalId;
    }

    public function handle(WithdrawalService $withdrawals): void
    {
        $withdrawals->approve($this->withdrawalId);
    }
}
