<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Deposit;
use App\Services\DepositService;

final readonly class DisapproveDepositAction
{
    public function __construct(private DepositService $deposits) {}

    public function execute(Deposit $deposit): Deposit
    {
        return $this->deposits->disapprove($deposit->id);
    }
}
