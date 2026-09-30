<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use RuntimeException;

final class DepositWithdrawalReportRequest extends FormRequest
{
    private const int DEFAULT_PER_PAGE = 15;

    private const int MAX_PER_PAGE = 100;

    /** Inclusive window length (in days) used when "from" is not given. */
    private const int DEFAULT_WINDOW_DAYS = 7;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('from') && $this->filled('to')
                    && $this->string('from')->toString() > $this->string('to')->toString()) {
                    $validator->errors()->add('to', 'The to date must be on or after the from date.');
                }
            },
        ];
    }

    public function from(): CarbonImmutable
    {
        if (! $this->filled('from')) {
            return $this->to()->subDays(self::DEFAULT_WINDOW_DAYS - 1);
        }

        return $this->parseDate($this->string('from')->toString());
    }

    public function to(): CarbonImmutable
    {
        if (! $this->filled('to')) {
            return CarbonImmutable::now()->startOfDay();
        }

        return $this->parseDate($this->string('to')->toString());
    }

    public function page(): int
    {
        return $this->integer('page', 1);
    }

    public function perPage(): int
    {
        return $this->integer('per_page', self::DEFAULT_PER_PAGE);
    }

    /**
     * The "date_format:Y-m-d" rule already guarantees $value parses cleanly
     * by the time this runs; the null case only exists to satisfy
     * createFromFormat()'s signature.
     */
    private function parseDate(string $value): CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('Y-m-d', $value);

        throw_if($date === null, RuntimeException::class, "Invalid date [{$value}]; expected Y-m-d format.");

        return $date->startOfDay();
    }
}
