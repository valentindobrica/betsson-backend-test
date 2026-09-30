<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class WithdrawRequest extends FormRequest
{
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
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
        ];
    }

    public function amountCents(): int
    {
        return Money::toCents($this->string('amount')->toString());
    }
}
