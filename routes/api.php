<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CustomerController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::apiResource('customers', CustomerController::class)->only([
        'store',
        'show',
        'update',
    ]);
});
