<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\CreateWithdrawalAction;
use App\Concerns\CastsDatabaseRowValues;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Database\Seeder;
use PDO;
use RuntimeException;

/**
 * Withdraws a portion of each customer's current balance, so it must run
 * after {@see DepositSeeder} has approved deposits to withdraw from.
 */
final class WithdrawalSeeder extends Seeder
{
    use CastsDatabaseRowValues;

    private const int MIN_WITHDRAWALS_PER_CUSTOMER = 0;

    private const int MAX_WITHDRAWALS_PER_CUSTOMER = 2;

    /** Never withdraw more than this share of the balance available at the time. */
    private const int MAX_PERCENTAGE_OF_BALANCE = 50;

    public function __construct(
        private readonly PDO $pdo,
        private readonly CustomerService $customers,
        private readonly WalletService $wallets,
        private readonly CreateWithdrawalAction $createWithdrawal,
        private readonly WithdrawalService $withdrawals,
    ) {}

    public function run(): void
    {
        $pendingWithdrawalIds = [];

        foreach ($this->customerIds() as $customerId) {
            $customer = $this->customers->find($customerId);

            throw_if(! $customer instanceof Customer, RuntimeException::class, "Customer [{$customerId}] disappeared while seeding withdrawals.");

            $withdrawalsToSeed = random_int(self::MIN_WITHDRAWALS_PER_CUSTOMER, self::MAX_WITHDRAWALS_PER_CUSTOMER);

            for ($i = 0; $i < $withdrawalsToSeed; $i++) {
                $withdrawalId = $this->createWithdrawalWithinBalance($customer);

                if ($withdrawalId !== null) {
                    $pendingWithdrawalIds[] = $withdrawalId;
                }
            }
        }

        if ($pendingWithdrawalIds === []) {
            return;
        }

        $this->withdrawals->markPendingAsInProgress();

        foreach ($pendingWithdrawalIds as $withdrawalId) {
            $this->withdrawals->approve($withdrawalId);
        }
    }

    private function createWithdrawalWithinBalance(Customer $customer): ?int
    {
        $wallet = $this->wallets->find($customer->id);

        throw_if($wallet === null, RuntimeException::class, "No wallet exists for customer [{$customer->id}].");

        $maxAmountCents = intdiv($wallet->balanceCents * self::MAX_PERCENTAGE_OF_BALANCE, 100);

        if ($maxAmountCents < 1) {
            return null;
        }

        return $this->createWithdrawal->execute($customer, random_int(1, $maxAmountCents))->id;
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
