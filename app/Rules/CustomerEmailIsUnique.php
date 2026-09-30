<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\CustomerService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final readonly class CustomerEmailIsUnique implements ValidationRule
{
    public function __construct(
        private CustomerService $customers,
        private ?int $ignoreCustomerId = null,
    ) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        if ($this->customers->emailExists($value, $this->ignoreCustomerId)) {
            $fail('The :attribute has already been taken.');
        }
    }
}
