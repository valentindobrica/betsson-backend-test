<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use PDO;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            PDO::class,
            static fn (Application $app): PDO => $app->make(DatabaseManager::class)->connection()->getPdo(),
        );
    }

    public function boot(): void
    {
        Route::bind('customer', function (string $value): Customer {
            throw_unless(ctype_digit($value), NotFoundHttpException::class);

            return $this->app->make(CustomerService::class)->find((int) $value) ?? throw new NotFoundHttpException();
        });
    }
}
