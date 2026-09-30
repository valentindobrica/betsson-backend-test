<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Deposit;
use App\Services\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Deposit $resource
 */
final class DepositResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'customer_id' => $this->resource->customerId,
            'status' => $this->resource->status->label(),
            'amount' => Money::toDecimalString($this->resource->amountCents),
            'bonus_amount' => Money::toDecimalString($this->resource->bonusAmountCents),
            'deposit_number' => $this->resource->depositNumber,
            'balance' => $this->resource->balanceAfterCents === null ? null : Money::toDecimalString($this->resource->balanceAfterCents),
            'bonus_balance' => $this->resource->bonusBalanceAfterCents === null ? null : Money::toDecimalString($this->resource->bonusBalanceAfterCents),
            'approved_at' => $this->resource->approvedAt?->toIso8601String(),
            'created_at' => $this->resource->createdAt->toIso8601String(),
            'updated_at' => $this->resource->updatedAt->toIso8601String(),
        ];
    }
}
