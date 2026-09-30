<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\CastsDatabaseRowValues;
use App\Enums\Gender;
use Carbon\CarbonImmutable;

/**
 * Plain data object representing a `customers` row.
 *
 * The application accesses the database through raw PDO statements
 * (see {@see \App\Services\CustomerService}) rather than Eloquent,
 * so this class is a read-only value object instead of an Eloquent model.
 */
final readonly class Customer
{
    use CastsDatabaseRowValues;

    public function __construct(
        public int $id,
        public Gender $gender,
        public string $firstName,
        public string $lastName,
        public string $country,
        public string $email,
        public int $bonusPercentage,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            id: self::toInt($row['id']),
            gender: Gender::from(self::toString($row['gender'])),
            firstName: self::toString($row['first_name']),
            lastName: self::toString($row['last_name']),
            country: self::toString($row['country']),
            email: self::toString($row['email']),
            bonusPercentage: self::toInt($row['bonus_percentage']),
            createdAt: CarbonImmutable::parse(self::toString($row['created_at'])),
            updatedAt: CarbonImmutable::parse(self::toString($row['updated_at'])),
        );
    }
}
