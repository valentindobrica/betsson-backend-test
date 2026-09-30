<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\Deposit;
use App\Services\DepositService;

final readonly class CreateDepositAction
{
    public function __construct(private DepositService $deposits) {}

    public function execute(Customer $customer, int $amountCents): Deposit
    {
        return $this->deposits->create($customer, $amountCents);
    }
}
