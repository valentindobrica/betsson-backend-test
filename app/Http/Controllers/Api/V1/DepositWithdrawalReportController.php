<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\DepositWithdrawalReportRequest;
use App\Http\Resources\DepositWithdrawalReportResource;
use App\Services\DepositWithdrawalReportService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class DepositWithdrawalReportController extends Controller
{
    public function __construct(private readonly DepositWithdrawalReportService $reports) {}

    public function __invoke(DepositWithdrawalReportRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->reports->summarize(
            from: $request->from(),
            to: $request->to(),
            page: $request->page(),
            perPage: $request->perPage(),
        );

        return DepositWithdrawalReportResource::collection($paginator);
    }
}
