<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;

final readonly class CreateWithdrawalAction
{
    public function __construct(private WithdrawalService $withdrawals) {}

    public function execute(Customer $customer, int $amountCents): Withdrawal
    {
        return $this->withdrawals->create($customer, $amountCents);
    }
}
