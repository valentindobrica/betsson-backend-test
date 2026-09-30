<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Gender;
use App\Models\Customer;
use App\Services\BonusPercentageGenerator;
use App\Services\CustomerService;
use App\Services\WalletService;
use Illuminate\Database\ConnectionInterface;

final readonly class CreateCustomerAction
{
    public function __construct(
        private CustomerService $customers,
        private WalletService $wallets,
        private BonusPercentageGenerator $bonusPercentage,
        private ConnectionInterface $connection,
    ) {}

    public function execute(
        Gender $gender,
        string $firstName,
        string $lastName,
        string $country,
        string $email,
    ): Customer {
        return $this->connection->transaction(function () use ($gender, $firstName, $lastName, $country, $email): Customer {
            $customer = $this->customers->create(
                gender: $gender,
                firstName: $firstName,
                lastName: $lastName,
                country: $country,
                email: $email,
                bonusPercentage: $this->bonusPercentage->generate(),
            );

            $this->wallets->initialize($customer->id);

            return $customer;
        });
    }
}
