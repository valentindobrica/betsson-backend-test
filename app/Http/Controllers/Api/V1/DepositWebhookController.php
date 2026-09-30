<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\ApproveDepositAction;
use App\Actions\DisapproveDepositAction;
use App\Http\Requests\DepositWebhookRequest;
use App\Http\Resources\DepositResource;
use App\Models\Deposit;
use Illuminate\Routing\Controller;

/**
 * Where a payment gateway would report a deposit's outcome.
 *
 * Idempotent against duplicate deliveries: see {@see \App\Services\DepositService}.
 */
final class DepositWebhookController extends Controller
{
    public function __construct(
        private readonly ApproveDepositAction $approve,
        private readonly DisapproveDepositAction $disapprove,
    ) {}

    public function __invoke(DepositWebhookRequest $request, Deposit $deposit): DepositResource
    {
        $resolved = $request->isApproved()
            ? $this->approve->execute($deposit)
            : $this->disapprove->execute($deposit);

        return DepositResource::make($resolved);
    }
}
