# Betsson Backend Test

A Laravel 13 / PHP 8.5 API that manages customers, their wallets, deposits and withdrawals, and a settlement report — built as a backend take-home test.

Application data access goes through raw PDO statements (no Eloquent/query builder), with row-level locking (`SELECT ... FOR UPDATE`) used to make webhook and job processing idempotent under concurrent calls. `Illuminate\Database\ConnectionInterface` is used purely for transaction demarcation.

## Requirements

- [Docker](https://www.docker.com/) (used to run [Laravel Sail](https://laravel.com/docs/sail): PHP 8.5, MySQL 8.4, Redis)
- Composer (only needed on the host to run `composer install` before Sail exists; if you don't have PHP/Composer locally, see the "No local PHP" note below)

## Getting Started

1. Clone the repository and move into it:

   ```bash
   git clone <repository-url> betsson-backend-test
   cd betsson-backend-test
   ```

2. Install PHP dependencies:

   ```bash
   composer install
   ```

   **No local PHP?** Use a throwaway container instead:

   ```bash
   docker run --rm \
       -u "$(id -u):$(id -g)" \
       -v "$(pwd):/var/www/html" \
       -w /var/www/html \
       laravelsail/php85-composer:latest \
       composer install --ignore-platform-reqs
   ```

3. Copy the environment file:

   ```bash
   cp .env.example .env
   ```

4. Point it at the services Sail's `compose.yaml` provides (the defaults are SQLite; the locking/concurrency design here is MySQL-specific, so use MySQL):

   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=mysql
   DB_PORT=3306
   DB_DATABASE=laravel
   DB_USERNAME=sail
   DB_PASSWORD=password
   ```

5. Start the containers:

   ```bash
   ./vendor/bin/sail up -d
   ```

6. Generate the app key and run migrations:

   ```bash
   ./vendor/bin/sail artisan key:generate
   ./vendor/bin/sail artisan migrate
   ```

7. (Optional) Seed sample data — 50 customers, each with approved deposits and withdrawals:

   ```bash
   ./vendor/bin/sail artisan db:seed
   ```

The API is now available at `http://localhost/api/v1`.

### Running the scheduler and queue

Withdrawals move from `Pending` to `Approved` via a scheduled command plus a queued job, not synchronously. In development, run:

```bash
./vendor/bin/sail composer dev
```

This starts the app server, queue worker, scheduler (`schedule:work`), log tailing (Pail), and Vite together. Without it, `withdrawals:process-pending` (which runs every 5 minutes, see `routes/console.php`) and its `ProcessWithdrawalJob` jobs won't actually fire.

## Running Tests

```bash
./vendor/bin/sail composer test
```

This runs, in order: Pint + Rector (`test:lint`), PHPStan at max strictness (`test:types`), 100% type coverage (`test:type-coverage`), and the full Pest suite with a 100% line-coverage gate (`test:unit`).

Individual steps:

```bash
./vendor/bin/sail composer test:unit           # Pest, Unit + Feature + Browser, 100% coverage required
./vendor/bin/sail composer test:types          # PHPStan (Larastan)
./vendor/bin/sail composer test:type-coverage  # Pest type coverage
./vendor/bin/sail pint                         # Fix code style
```

A separate suite proves row-locking is safe under real concurrent processes (via `pcntl_fork`). It's excluded from `composer test` because forking inside a live test-runner TUI can corrupt the terminal, so it's run on its own:

```bash
./vendor/bin/sail composer test:concurrency
```

## API Overview

All routes are prefixed with `/api/v1`.

| Method | URI | Description |
|---|---|---|
| POST | `/customers` | Create a customer (also creates their wallet) |
| GET | `/customers/{customer}` | Show a customer |
| PUT/PATCH | `/customers/{customer}` | Update a customer |
| GET | `/customers/{customer}/deposits` | List a customer's deposits |
| POST | `/customers/{customer}/deposits` | Create a pending deposit |
| POST | `/deposits/{deposit}/webhook` | Payment gateway webhook — approves/disapproves a deposit (idempotent) |
| GET | `/customers/{customer}/withdrawals` | List a customer's withdrawals |
| POST | `/customers/{customer}/withdrawals` | Create a withdrawal (reserves the balance immediately) |
| GET | `/reports/deposits-withdrawals` | Paginated report of approved deposits/withdrawals, grouped by settlement date and country |

## Tech Stack

- PHP 8.5, Laravel 13
- MySQL 8.4 (via Sail), Redis
- Pest 5 (+ type coverage plugin), Larastan 3, Pint, Rector
- GitHub Actions CI (mirrors the local Sail/MySQL setup)
