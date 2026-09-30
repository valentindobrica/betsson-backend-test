<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DepositStatus;
use App\Models\Customer;
use App\Models\Deposit;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use PDO;
use RuntimeException;

/**
 * Creates deposits and resolves them to Approved/Disapproved.
 *
 * A deposit is created Pending and does not touch the wallet. Resolving it
 * (normally triggered by a payment gateway webhook) is the only place the
 * wallet is credited, and is made idempotent against duplicate webhook
 * deliveries: {@see approve()}/{@see disapprove()} lock the deposit row
 * first, and if it is no longer Pending - because an earlier, possibly
 * concurrent call already resolved it - they simply return it unchanged
 * instead of crediting the wallet a second time.
 */
final readonly class DepositService
{
    private const int DEPOSITS_PER_BONUS = 3;

    public function __construct(
        private PDO $pdo,
        private WalletService $wallets,
        private CustomerService $customers,
        private ConnectionInterface $connection,
    ) {}

    public function create(Customer $customer, int $amountCents): Deposit
    {
        $now = CarbonImmutable::now();

        $statement = $this->pdo->prepare(
            'INSERT INTO deposits (customer_id, status_id, amount, created_at, updated_at)
             VALUES (:customer_id, :status_id, :amount, :created_at, :updated_at)'
        );

        $statement->execute([
            'customer_id' => $customer->id,
            'status_id' => DepositStatus::Pending->value,
            'amount' => Money::toDecimalString($amountCents),
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);

        return new Deposit(
            id: (int) $this->pdo->lastInsertId(),
            customerId: $customer->id,
            status: DepositStatus::Pending,
            amountCents: $amountCents,
            bonusAmountCents: 0,
            depositNumber: null,
            balanceAfterCents: null,
            bonusBalanceAfterCents: null,
            approvedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function find(int $depositId): ?Deposit
    {
        $statement = $this->pdo->prepare('SELECT * FROM deposits WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $depositId]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : Deposit::fromDatabaseRow($row);
    }

    public function approve(int $depositId): Deposit
    {
        return $this->connection->transaction(function () use ($depositId): Deposit {
            $deposit = $this->lockDeposit($depositId);

            if ($deposit->status !== DepositStatus::Pending) {
                return $deposit;
            }

            $customer = $this->customers->find($deposit->customerId);
            throw_if(! $customer instanceof Customer, RuntimeException::class, "Customer [{$deposit->customerId}] for deposit [{$depositId}] no longer exists.");

            $wallet = $this->wallets->lockForUpdate($deposit->customerId);
            $depositNumber = $wallet->approvedDepositCount + 1;

            $bonusCents = $depositNumber % self::DEPOSITS_PER_BONUS === 0
                ? intdiv($deposit->amountCents * $customer->bonusPercentage, 100)
                : 0;

            $newBalanceCents = $wallet->balanceCents + $deposit->amountCents;
            $newBonusBalanceCents = $wallet->bonusBalanceCents + $bonusCents;

            $this->wallets->creditDeposit($deposit->customerId, $newBalanceCents, $newBonusBalanceCents, $depositNumber);

            return $this->resolve(
                deposit: $deposit,
                status: DepositStatus::Approved,
                bonusAmountCents: $bonusCents,
                depositNumber: $depositNumber,
                balanceAfterCents: $newBalanceCents,
                bonusBalanceAfterCents: $newBonusBalanceCents,
            );
        });
    }

    public function disapprove(int $depositId): Deposit
    {
        return $this->connection->transaction(function () use ($depositId): Deposit {
            $deposit = $this->lockDeposit($depositId);

            if ($deposit->status !== DepositStatus::Pending) {
                return $deposit;
            }

            return $this->resolve(
                deposit: $deposit,
                status: DepositStatus::Disapproved,
                bonusAmountCents: 0,
                depositNumber: null,
                balanceAfterCents: null,
                bonusBalanceAfterCents: null,
            );
        });
    }

    /**
     * @return LengthAwarePaginator<int, Deposit>
     */
    public function paginate(int $customerId, int $page, int $perPage): LengthAwarePaginator
    {
        $countStatement = $this->pdo->prepare('SELECT COUNT(*) FROM deposits WHERE customer_id = :customer_id');
        $countStatement->execute(['customer_id' => $customerId]);

        $total = (int) $countStatement->fetchColumn();

        $statement = $this->pdo->prepare(
            'SELECT * FROM deposits WHERE customer_id = :customer_id ORDER BY id DESC LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue('customer_id', $customerId, PDO::PARAM_INT);
        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        $items = [];

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $items[] = Deposit::fromDatabaseRow($row);
        }

        return new LengthAwarePaginator($items, $total, $perPage, $page);
    }

    /**
     * Lock the deposit row for the remainder of the current transaction.
     * This is what makes approve()/disapprove() idempotent: whichever call
     * gets the lock first sees status Pending and resolves it; any other
     * concurrent or later call sees the already-resolved status and no-ops.
     */
    private function lockDeposit(int $depositId): Deposit
    {
        $forUpdate = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';

        $statement = $this->pdo->prepare(
            "SELECT * FROM deposits WHERE id = :id LIMIT 1{$forUpdate}"
        );
        $statement->execute(['id' => $depositId]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        throw_if($row === false, RuntimeException::class, "No deposit exists with id [{$depositId}].");

        return Deposit::fromDatabaseRow($row);
    }

    private function resolve(
        Deposit $deposit,
        DepositStatus $status,
        int $bonusAmountCents,
        ?int $depositNumber,
        ?int $balanceAfterCents,
        ?int $bonusBalanceAfterCents,
    ): Deposit {
        $now = CarbonImmutable::now();

        $statement = $this->pdo->prepare(
            'UPDATE deposits
             SET status_id = :status_id, bonus_amount = :bonus_amount, deposit_number = :deposit_number,
                 balance_after = :balance_after, bonus_balance_after = :bonus_balance_after,
                 approved_at = :approved_at, updated_at = :updated_at
             WHERE id = :id'
        );

        $statement->execute([
            'status_id' => $status->value,
            'bonus_amount' => Money::toDecimalString($bonusAmountCents),
            'deposit_number' => $depositNumber,
            'balance_after' => $balanceAfterCents === null ? null : Money::toDecimalString($balanceAfterCents),
            'bonus_balance_after' => $bonusBalanceAfterCents === null ? null : Money::toDecimalString($bonusBalanceAfterCents),
            'approved_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
            'id' => $deposit->id,
        ]);

        return new Deposit(
            id: $deposit->id,
            customerId: $deposit->customerId,
            status: $status,
            amountCents: $deposit->amountCents,
            bonusAmountCents: $bonusAmountCents,
            depositNumber: $depositNumber,
            balanceAfterCents: $balanceAfterCents,
            bonusBalanceAfterCents: $bonusBalanceAfterCents,
            approvedAt: $now,
            createdAt: $deposit->createdAt,
            updatedAt: $now,
        );
    }
}
