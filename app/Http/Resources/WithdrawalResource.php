<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Withdrawal;
use App\Services\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Withdrawal $resource
 */
final class WithdrawalResource extends JsonResource
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
            'pre_approved' => $this->resource->preApproved,
            'balance' => Money::toDecimalString($this->resource->balanceAfterCents),
            'processed_at' => $this->resource->processedAt?->toIso8601String(),
            'created_at' => $this->resource->createdAt->toIso8601String(),
            'updated_at' => $this->resource->updatedAt->toIso8601String(),
        ];
    }
}
