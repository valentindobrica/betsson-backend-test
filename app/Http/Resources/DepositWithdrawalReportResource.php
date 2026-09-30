<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DepositWithdrawalReportRow;
use App\Services\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read DepositWithdrawalReportRow $resource
 */
final class DepositWithdrawalReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->resource->date,
            'country' => $this->resource->country,
            'unique_customers' => $this->resource->uniqueCustomers,
            'deposits_count' => $this->resource->depositsCount,
            'deposits_total' => Money::toDecimalString($this->resource->depositsTotalCents),
            'withdrawals_count' => $this->resource->withdrawalsCount,
            'withdrawals_total' => $this->signedWithdrawalsTotal(),
        ];
    }

    /**
     * Rendered as a negative amount (e.g. "-200.45"), matching the task
     * spec's own example table; zero stays unsigned.
     */
    private function signedWithdrawalsTotal(): string
    {
        $cents = $this->resource->withdrawalsTotalCents;

        return ($cents > 0 ? '-' : '').Money::toDecimalString($cents);
    }
}
