<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Gender;
use App\Models\Customer;
use App\Services\BonusPercentageGenerator;
use App\Services\CustomerService;

final readonly class CreateCustomerAction
{
    public function __construct(
        private CustomerService $customers,
        private BonusPercentageGenerator $bonusPercentage,
    ) {}

    public function execute(
        Gender $gender,
        string $firstName,
        string $lastName,
        string $country,
        string $email,
    ): Customer {
        return $this->customers->create(
            gender: $gender,
            firstName: $firstName,
            lastName: $lastName,
            country: $country,
            email: $email,
            bonusPercentage: $this->bonusPercentage->generate(),
        );
    }
}
