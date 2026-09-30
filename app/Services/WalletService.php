<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Wallet;
use Carbon\CarbonImmutable;
use PDO;
use RuntimeException;

/**
 * Reads, initializes, and locks a customer's wallet using raw PDO statements.
 *
 * {@see lockForUpdate()} must only be called inside a transaction (started
 * via `ConnectionInterface::transaction()` / `beginTransaction()`), by
 * {@see DepositService} and {@see WithdrawalService}, so the lock is held
 * for the whole read-check-write sequence that mutates the wallet.
 */
final readonly class WalletService
{
    public function __construct(private PDO $pdo) {}

    public function initialize(int $customerId): Wallet
    {
        $now = CarbonImmutable::now();

        $statement = $this->pdo->prepare(
            'INSERT INTO wallets (customer_id, balance, bonus_balance, created_at, updated_at)
             VALUES (:customer_id, 0, 0, :created_at, :updated_at)'
        );

        $statement->execute([
            'customer_id' => $customerId,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);

        return new Wallet(
            customerId: $customerId,
            balanceCents: 0,
            bonusBalanceCents: 0,
            approvedDepositCount: 0,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function find(int $customerId): ?Wallet
    {
        $statement = $this->pdo->prepare('SELECT * FROM wallets WHERE customer_id = :customer_id LIMIT 1');
        $statement->execute(['customer_id' => $customerId]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : Wallet::fromDatabaseRow($row);
    }

    /**
     * Lock the customer's wallet row for the remainder of the current
     * transaction, so no other request can read or write it until this one
     * commits or rolls back. Must be called inside a transaction.
     */
    public function lockForUpdate(int $customerId): Wallet
    {
        $forUpdate = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';

        $statement = $this->pdo->prepare(
            "SELECT * FROM wallets WHERE customer_id = :customer_id LIMIT 1{$forUpdate}"
        );
        $statement->execute(['customer_id' => $customerId]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        throw_if($row === false, RuntimeException::class, "No wallet exists for customer [{$customerId}].");

        return Wallet::fromDatabaseRow($row);
    }

    /**
     * Debit the real balance for a withdrawal. Never touches bonus_balance:
     * a withdrawal can only ever spend real money, so there is no value to
     * pass for it here, unlike {@see creditDeposit()}.
     */
    public function debitBalance(int $customerId, int $balanceCents): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE wallets SET balance = :balance, updated_at = :updated_at WHERE customer_id = :customer_id'
        );

        $statement->execute([
            'balance' => Money::toDecimalString($balanceCents),
            'updated_at' => CarbonImmutable::now()->toDateTimeString(),
            'customer_id' => $customerId,
        ]);
    }

    /**
     * Credit an approved deposit, bumping the approved-deposit counter in
     * the same write as the balance. Keeping the counter on this row (and
     * writing it together with the balance under the same lock) is what
     * makes the every-3rd-deposit bonus immune to the stale-snapshot read a
     * separate `COUNT()` against the deposits table would be exposed to
     * under REPEATABLE READ, even while holding this row's lock.
     */
    public function creditDeposit(int $customerId, int $balanceCents, int $bonusBalanceCents, int $approvedDepositCount): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE wallets
             SET balance = :balance, bonus_balance = :bonus_balance,
                 approved_deposit_count = :approved_deposit_count, updated_at = :updated_at
             WHERE customer_id = :customer_id'
        );

        $statement->execute([
            'balance' => Money::toDecimalString($balanceCents),
            'bonus_balance' => Money::toDecimalString($bonusBalanceCents),
            'approved_deposit_count' => $approvedDepositCount,
            'updated_at' => CarbonImmutable::now()->toDateTimeString(),
            'customer_id' => $customerId,
        ]);
    }
}
