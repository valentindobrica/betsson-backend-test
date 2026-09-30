<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Rules\CustomerEmailIsUnique;
use App\Services\CustomerService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCustomerRequest extends FormRequest
{
    public function __construct(private readonly CustomerService $customers)
    {
        parent::__construct();
    }

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
            'gender' => ['required', Rule::enum(Gender::class)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'email' => ['required', 'string', 'email', 'max:255', new CustomerEmailIsUnique($this->customers)],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->input('email')) ? mb_strtolower(mb_trim($this->input('email'))) : $this->input('email'),
            'country' => is_string($this->input('country')) ? mb_strtoupper(mb_trim($this->input('country'))) : $this->input('country'),
        ]);
    }
}
