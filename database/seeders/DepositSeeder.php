<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\ApproveDepositAction;
use App\Actions\CreateDepositAction;
use App\Concerns\CastsDatabaseRowValues;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Database\Seeder;
use PDO;
use RuntimeException;

final class DepositSeeder extends Seeder
{
    use CastsDatabaseRowValues;

    private const int MIN_DEPOSITS_PER_CUSTOMER = 1;

    private const int MAX_DEPOSITS_PER_CUSTOMER = 5;

    private const int MIN_AMOUNT_CENTS = 1_000;

    private const int MAX_AMOUNT_CENTS = 50_000;

    public function __construct(
        private readonly PDO $pdo,
        private readonly CustomerService $customers,
        private readonly CreateDepositAction $createDeposit,
        private readonly ApproveDepositAction $approveDeposit,
    ) {}

    public function run(): void
    {
        foreach ($this->customerIds() as $customerId) {
            $customer = $this->customers->find($customerId);

            throw_if(! $customer instanceof Customer, RuntimeException::class, "Customer [{$customerId}] disappeared while seeding deposits.");

            $depositsToSeed = random_int(self::MIN_DEPOSITS_PER_CUSTOMER, self::MAX_DEPOSITS_PER_CUSTOMER);

            for ($i = 0; $i < $depositsToSeed; $i++) {
                $deposit = $this->createDeposit->execute($customer, random_int(self::MIN_AMOUNT_CENTS, self::MAX_AMOUNT_CENTS));
                $this->approveDeposit->execute($deposit);
            }
        }
    }

    /**
     * @return list<int>
     */
    private function customerIds(): array
    {
        $statement = $this->pdo->prepare('SELECT id FROM customers ORDER BY id');
        $statement->execute();

        $ids = [];

        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $row) {
            $ids[] = self::toInt($row);
        }

        return $ids;
    }
}
