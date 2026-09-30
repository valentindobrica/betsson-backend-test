<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Customer $resource
 */
final class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'gender' => $this->resource->gender->value,
            'first_name' => $this->resource->firstName,
            'last_name' => $this->resource->lastName,
            'country' => $this->resource->country,
            'email' => $this->resource->email,
            'bonus_percentage' => $this->resource->bonusPercentage,
            'created_at' => $this->resource->createdAt->toIso8601String(),
            'updated_at' => $this->resource->updatedAt->toIso8601String(),
        ];
    }
}
