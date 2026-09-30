<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateCustomerAction;
use App\Actions\UpdateCustomerAction;
use App\Enums\Gender;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class CustomerController extends Controller
{
    public function __construct(
        private readonly CreateCustomerAction $createCustomer,
        private readonly UpdateCustomerAction $updateCustomer,
    ) {}

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->createCustomer->execute(
            gender: Gender::from($request->string('gender')->toString()),
            firstName: $request->string('first_name')->toString(),
            lastName: $request->string('last_name')->toString(),
            country: $request->string('country')->toString(),
            email: $request->string('email')->toString(),
        );

        return CustomerResource::make($customer)->response()->setStatusCode(201);
    }

    public function show(Customer $customer): CustomerResource
    {
        return CustomerResource::make($customer);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {
        $updated = $this->updateCustomer->execute(
            customer: $customer,
            gender: Gender::from($request->string('gender')->toString()),
            firstName: $request->string('first_name')->toString(),
            lastName: $request->string('last_name')->toString(),
            country: $request->string('country')->toString(),
            email: $request->string('email')->toString(),
        );

        return CustomerResource::make($updated);
    }
}
