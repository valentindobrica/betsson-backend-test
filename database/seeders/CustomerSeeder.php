<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\CreateCustomerAction;
use App\Enums\Gender;
use Illuminate\Database\Seeder;

final class CustomerSeeder extends Seeder
{
    private const int CUSTOMERS_TO_SEED = 50;

    public function __construct(private readonly CreateCustomerAction $createCustomer) {}

    public function run(): void
    {
        $genders = Gender::cases();

        for ($i = 0; $i < self::CUSTOMERS_TO_SEED; $i++) {
            $this->createCustomer->execute(
                gender: $genders[random_int(0, count($genders) - 1)],
                firstName: fake()->firstName(),
                lastName: fake()->lastName(),
                country: fake()->countryCode(),
                email: fake()->unique()->safeEmail(),
            );
        }
    }
}
