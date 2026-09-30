<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerDepositController;
use App\Http\Controllers\Api\V1\CustomerWithdrawalController;
use App\Http\Controllers\Api\V1\DepositWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::apiResource('customers', CustomerController::class)->only([
        'store',
        'show',
        'update',
    ]);

    Route::apiResource('customers.deposits', CustomerDepositController::class)->only([
        'index',
        'store',
    ]);

    Route::apiResource('customers.withdrawals', CustomerWithdrawalController::class)->only([
        'index',
        'store',
    ]);

    Route::post('deposits/{deposit}/webhook', DepositWebhookController::class)->name('deposits.webhook');
});
