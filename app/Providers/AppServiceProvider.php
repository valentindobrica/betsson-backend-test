<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Customer;
use App\Models\Deposit;
use App\Services\CustomerService;
use App\Services\DepositService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\DevCommands;
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

        // Bound so services can inject ConnectionInterface for transaction demarcation
        // (begin/commit/rollback) only. All actual reads/writes still go through the
        // raw PDO above; Laravel's connection wrapper is used here purely because it
        // supports nested (savepoint-based) transactions, which raw PDO does not, and
        // which RefreshDatabase relies on in tests.
        $this->app->bind(
            ConnectionInterface::class,
            static fn (Application $app): ConnectionInterface => $app->make(DatabaseManager::class)->connection(),
        );
    }

    public function boot(): void
    {
        // Without this, `composer run dev` starts serve/queue:listen/pail/vite but
        // never the scheduler, so anything registered in routes/console.php via
        // Schedule::* (e.g. withdrawals:process-pending) is registered but never
        // actually triggered in local development.
        DevCommands::artisan('schedule:work', 'schedule');

        Route::bind('customer', function (string $value): Customer {
            throw_unless(ctype_digit($value), NotFoundHttpException::class);

            return $this->app->make(CustomerService::class)->find((int) $value) ?? throw new NotFoundHttpException();
        });

        Route::bind('deposit', function (string $value): Deposit {
            throw_unless(ctype_digit($value), NotFoundHttpException::class);

            return $this->app->make(DepositService::class)->find((int) $value) ?? throw new NotFoundHttpException();
        });
    }
}
