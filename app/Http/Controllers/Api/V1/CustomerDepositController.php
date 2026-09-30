<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateDepositAction;
use App\Http\Requests\DepositRequest;
use App\Http\Requests\ListCustomerTransactionsRequest;
use App\Http\Resources\DepositResource;
use App\Models\Customer;
use App\Services\DepositService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class CustomerDepositController extends Controller
{
    public function __construct(
        private readonly CreateDepositAction $createDeposit,
        private readonly DepositService $deposits,
    ) {}

    public function index(ListCustomerTransactionsRequest $request, Customer $customer): AnonymousResourceCollection
    {
        $paginator = $this->deposits->paginate($customer->id, $request->page(), $request->perPage());

        return DepositResource::collection($paginator);
    }

    public function store(DepositRequest $request, Customer $customer): JsonResponse
    {
        $deposit = $this->createDeposit->execute($customer, $request->amountCents());

        return DepositResource::make($deposit)->response()->setStatusCode(201);
    }
}
