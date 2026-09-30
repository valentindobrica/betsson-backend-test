<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Gender;
use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Persists and retrieves customers using raw PDO statements.
 */
final readonly class CustomerService
{
    /** SQLSTATE class for integrity constraint violations (e.g. unique key clashes). */
    private const string INTEGRITY_CONSTRAINT_VIOLATION = '23000';

    public function __construct(private PDO $pdo) {}

    public function create(
        Gender $gender,
        string $firstName,
        string $lastName,
        string $country,
        string $email,
        int $bonusPercentage,
    ): Customer {
        $now = CarbonImmutable::now();

        $statement = $this->pdo->prepare(
            'INSERT INTO customers (gender, first_name, last_name, country, email, bonus_percentage, created_at, updated_at)
             VALUES (:gender, :first_name, :last_name, :country, :email, :bonus_percentage, :created_at, :updated_at)'
        );

        $this->executeGuardingEmailUniqueness($statement, [
            'gender' => $gender->value,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'country' => $country,
            'email' => $email,
            'bonus_percentage' => $bonusPercentage,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);

        return new Customer(
            id: (int) $this->pdo->lastInsertId(),
            gender: $gender,
            firstName: $firstName,
            lastName: $lastName,
            country: $country,
            email: $email,
            bonusPercentage: $bonusPercentage,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function update(
        Customer $customer,
        Gender $gender,
        string $firstName,
        string $lastName,
        string $country,
        string $email,
    ): Customer {
        $now = CarbonImmutable::now();

        $statement = $this->pdo->prepare(
            'UPDATE customers
             SET gender = :gender, first_name = :first_name, last_name = :last_name,
                 country = :country, email = :email, updated_at = :updated_at
             WHERE id = :id'
        );

        $this->executeGuardingEmailUniqueness($statement, [
            'gender' => $gender->value,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'country' => $country,
            'email' => $email,
            'updated_at' => $now->toDateTimeString(),
            'id' => $customer->id,
        ]);

        return new Customer(
            id: $customer->id,
            gender: $gender,
            firstName: $firstName,
            lastName: $lastName,
            country: $country,
            email: $email,
            bonusPercentage: $customer->bonusPercentage,
            createdAt: $customer->createdAt,
            updatedAt: $now,
        );
    }

    public function find(int $id): ?Customer
    {
        $statement = $this->pdo->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : Customer::fromDatabaseRow($row);
    }

    public function emailExists(string $email, ?int $ignoreCustomerId = null): bool
    {
        $sql = 'SELECT EXISTS(SELECT 1 FROM customers WHERE email = :email';
        $bindings = ['email' => $email];

        if ($ignoreCustomerId !== null) {
            $sql .= ' AND id <> :ignore_customer_id';
            $bindings['ignore_customer_id'] = $ignoreCustomerId;
        }

        $sql .= ') AS email_exists';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return (bool) $statement->fetchColumn();
    }

    /**
     * Execute an insert/update statement, converting a unique email clash into the same
     * validation error the pre-check in {@see \App\Rules\CustomerEmailIsUnique} produces.
     *
     * The pre-check cannot fully rule out a race between two concurrent requests for the
     * same email, so the database's own unique constraint remains the final guard.
     *
     * @param  array<string, mixed>  $bindings
     */
    private function executeGuardingEmailUniqueness(PDOStatement $statement, array $bindings): void
    {
        try {
            $statement->execute($bindings);
        } catch (PDOException $pdoException) {
            if ($pdoException->getCode() === self::INTEGRITY_CONSTRAINT_VIOLATION) {
                throw ValidationException::withMessages([
                    'email' => 'The email has already been taken.',
                ]);
            }

            throw $pdoException;
        }
    }
}
