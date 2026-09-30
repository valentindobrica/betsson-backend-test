<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateWithdrawalAction;
use App\Http\Requests\ListCustomerTransactionsRequest;
use App\Http\Requests\WithdrawRequest;
use App\Http\Resources\WithdrawalResource;
use App\Models\Customer;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class CustomerWithdrawalController extends Controller
{
    public function __construct(
        private readonly CreateWithdrawalAction $createWithdrawal,
        private readonly WithdrawalService $withdrawals,
    ) {}

    public function index(ListCustomerTransactionsRequest $request, Customer $customer): AnonymousResourceCollection
    {
        $paginator = $this->withdrawals->paginate($customer->id, $request->page(), $request->perPage());

        return WithdrawalResource::collection($paginator);
    }

    public function store(WithdrawRequest $request, Customer $customer): JsonResponse
    {
        $withdrawal = $this->createWithdrawal->execute($customer, $request->amountCents());

        return WithdrawalResource::make($withdrawal)->response()->setStatusCode(201);
    }
}
