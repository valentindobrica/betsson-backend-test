<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\CastsDatabaseRowValues;
use App\Enums\WithdrawStatus;
use App\Models\Customer;
use App\Models\Withdrawal;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use PDO;
use RuntimeException;

/**
 * Creates withdrawals and carries them through their processing lifecycle.
 *
 * Unlike deposits, the amount is reserved from the wallet immediately on
 * {@see create()} (locked the same way {@see DepositService::approve()}
 * locks it), since "balance can never go below 0" must hold at request
 * time. {@see markPendingAsInProgress()} and {@see approve()} back the
 * scheduled command and its processing job: the command marks a batch of
 * withdrawals pre-approved, and the job - guarded the same idempotent way
 * as a deposit webhook, via a row lock plus a status/pre_approved check -
 * finalizes exactly one of them to Approved even if it somehow ran twice.
 */
final readonly class WithdrawalService
{
    use CastsDatabaseRowValues;

    public function __construct(
        private PDO $pdo,
        private WalletService $wallets,
        private ConnectionInterface $connection,
    ) {}

    public function create(Customer $customer, int $amountCents): Withdrawal
    {
        return $this->connection->transaction(function () use ($customer, $amountCents): Withdrawal {
            $wallet = $this->wallets->lockForUpdate($customer->id);

            if ($amountCents > $wallet->balanceCents) {
                throw ValidationException::withMessages([
                    'amount' => 'The customer does not have sufficient balance for this withdrawal.',
                ]);
            }

            $newBalanceCents = $wallet->balanceCents - $amountCents;
            $this->wallets->debitBalance($customer->id, $newBalanceCents);

            $now = CarbonImmutable::now();

            $statement = $this->pdo->prepare(
                'INSERT INTO withdrawals (customer_id, status_id, amount, pre_approved, balance_after, created_at, updated_at)
                 VALUES (:customer_id, :status_id, :amount, 0, :balance_after, :created_at, :updated_at)'
            );

            $statement->execute([
                'customer_id' => $customer->id,
                'status_id' => WithdrawStatus::Pending->value,
                'amount' => Money::toDecimalString($amountCents),
                'balance_after' => Money::toDecimalString($newBalanceCents),
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ]);

            return new Withdrawal(
                id: (int) $this->pdo->lastInsertId(),
                customerId: $customer->id,
                status: WithdrawStatus::Pending,
                amountCents: $amountCents,
                preApproved: false,
                balanceAfterCents: $newBalanceCents,
                processedAt: null,
                createdAt: $now,
                updatedAt: $now,
            );
        });
    }

    public function find(int $withdrawalId): ?Withdrawal
    {
        $statement = $this->pdo->prepare('SELECT * FROM withdrawals WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $withdrawalId]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : Withdrawal::fromDatabaseRow($row);
    }

    /**
     * @return LengthAwarePaginator<int, Withdrawal>
     */
    public function paginate(int $customerId, int $page, int $perPage): LengthAwarePaginator
    {
        $countStatement = $this->pdo->prepare('SELECT COUNT(*) FROM withdrawals WHERE customer_id = :customer_id');
        $countStatement->execute(['customer_id' => $customerId]);

        $total = (int) $countStatement->fetchColumn();

        $statement = $this->pdo->prepare(
            'SELECT * FROM withdrawals WHERE customer_id = :customer_id ORDER BY id DESC LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue('customer_id', $customerId, PDO::PARAM_INT);
        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        $items = [];

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $items[] = Withdrawal::fromDatabaseRow($row);
        }

        return new LengthAwarePaginator($items, $total, $perPage, $page);
    }

    /**
     * Bulk-transition every Pending withdrawal to InProgress + pre-approved.
     * A plain UPDATE is enough here (no per-row locking): Pending is only
     * ever written once, at creation, so there is nothing for this to race
     * against for a given row.
     *
     * @return list<int> ids of the withdrawals that were moved, so the
     *                   caller can dispatch a processing job per id
     */
    public function markPendingAsInProgress(): array
    {
        $selectStatement = $this->pdo->prepare('SELECT id FROM withdrawals WHERE status_id = :status');
        $selectStatement->execute(['status' => WithdrawStatus::Pending->value]);

        $ids = [];

        foreach ($selectStatement->fetchAll(PDO::FETCH_COLUMN) as $row) {
            $ids[] = self::toInt($row);
        }

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $updateStatement = $this->pdo->prepare(
            "UPDATE withdrawals SET status_id = ?, pre_approved = 1, updated_at = ? WHERE id IN ({$placeholders})"
        );
        $updateStatement->execute([WithdrawStatus::InProgress->value, CarbonImmutable::now()->toDateTimeString(), ...$ids]);

        return $ids;
    }

    /**
     * Finalize a pre-approved, in-progress withdrawal to Approved.
     *
     * Locks the row first: if it is not (still) InProgress and pre-approved
     * - because an earlier run of the same job already finalized it - this
     * returns it unchanged instead of processing it again. This is the same
     * idempotency guard as a deposit webhook, applied to the queued job in
     * case its ShouldBeUnique lock is ever bypassed (e.g. a manual retry).
     */
    public function approve(int $withdrawalId): Withdrawal
    {
        return $this->connection->transaction(function () use ($withdrawalId): Withdrawal {
            $withdrawal = $this->lockWithdrawal($withdrawalId);

            if (! $withdrawal->preApproved || $withdrawal->status !== WithdrawStatus::InProgress) {
                return $withdrawal;
            }

            $now = CarbonImmutable::now();

            $statement = $this->pdo->prepare(
                'UPDATE withdrawals SET status_id = :status_id, processed_at = :processed_at, updated_at = :updated_at
                 WHERE id = :id'
            );

            $statement->execute([
                'status_id' => WithdrawStatus::Approved->value,
                'processed_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
                'id' => $withdrawalId,
            ]);

            return new Withdrawal(
                id: $withdrawal->id,
                customerId: $withdrawal->customerId,
                status: WithdrawStatus::Approved,
                amountCents: $withdrawal->amountCents,
                preApproved: $withdrawal->preApproved,
                balanceAfterCents: $withdrawal->balanceAfterCents,
                processedAt: $now,
                createdAt: $withdrawal->createdAt,
                updatedAt: $now,
            );
        });
    }

    private function lockWithdrawal(int $withdrawalId): Withdrawal
    {
        $forUpdate = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';

        $statement = $this->pdo->prepare(
            "SELECT * FROM withdrawals WHERE id = :id LIMIT 1{$forUpdate}"
        );
        $statement->execute(['id' => $withdrawalId]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        throw_if($row === false, RuntimeException::class, "No withdrawal exists with id [{$withdrawalId}].");

        return Withdrawal::fromDatabaseRow($row);
    }
}
