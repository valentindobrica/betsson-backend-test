<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessWithdrawalJob;
use App\Services\WithdrawalService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Moves every Pending withdrawal to InProgress, pre-approves it, and queues
 * a {@see ProcessWithdrawalJob} to finalize it. Scheduled every 5 minutes,
 * see routes/console.php.
 */
#[Signature('withdrawals:process-pending')]
#[Description('Move pending withdrawals to in-progress, pre-approve them, and queue them for processing.')]
final class ProcessPendingWithdrawalsCommand extends Command
{
    public function handle(WithdrawalService $withdrawals): int
    {
        $ids = $withdrawals->markPendingAsInProgress();

        foreach ($ids as $id) {
            dispatch(new ProcessWithdrawalJob($id));
        }

        $this->info(count($ids).' withdrawal(s) moved to in-progress and queued for processing.');

        return self::SUCCESS;
    }
}
