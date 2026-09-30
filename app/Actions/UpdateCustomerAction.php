<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Gender;
use App\Models\Customer;
use App\Services\CustomerService;

final readonly class UpdateCustomerAction
{
    public function __construct(private CustomerService $customers) {}

    public function execute(
        Customer $customer,
        Gender $gender,
        string $firstName,
        string $lastName,
        string $country,
        string $email,
    ): Customer {
        return $this->customers->update(
            customer: $customer,
            gender: $gender,
            firstName: $firstName,
            lastName: $lastName,
            country: $country,
            email: $email,
        );
    }
}
