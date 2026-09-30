<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        Str::createRandomStringsNormally();
        Str::createUuidsNormally();
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        Sleep::fake();

        $this->freezeTime();
    })
    ->in('Browser', 'Feature', 'Unit');

// Concurrency tests fork real OS processes that need their own database
// connections, which must see genuinely committed data (RefreshDatabase's
// per-test transaction is invisible to other connections). Tables are
// truncated by hand instead of via RefreshDatabase/DatabaseTruncation,
// deliberately leaving deposit_statuses/withdraw_statuses alone: they are
// seeded once, in their migration, and never reseeded on truncation.
pest()->extend(TestCase::class)
    ->beforeEach(function (): void {
        $this->artisan('migrate');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (['withdrawals', 'deposits', 'wallets', 'customers'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    })
    ->in('Concurrency');

expect()->extend('toBeOne', fn () => $this->toBe(1));

function something(): void
{
    // ..
}

/**
 * Force the next database query, in this process, onto a brand new
 * connection. A forked child inherits the parent's MySQL socket; closing a
 * connection on one side of that shared socket (as reconnecting does)
 * terminates it for every process still holding it, so every process
 * involved in a fork - parent included, once its children are done - must
 * call this before it can be trusted to touch the database again.
 */
function reconnectDatabase(): void
{
    DB::purge();
    app()->forgetInstance(PDO::class);
}

/**
 * Fork a child process that runs $work against its own, fresh database
 * connection, then exits. Used by the Concurrency suite to fire genuinely
 * simultaneous requests at the same row and prove locking serializes them.
 * Returns the child's PID so the caller can pcntl_waitpid() on it.
 */
function forkAndRun(Closure $work): int
{
    $pid = pcntl_fork();

    throw_if($pid === -1, RuntimeException::class, 'Unable to fork a process for the concurrency test.');

    if ($pid === 0) {
        reconnectDatabase();

        try {
            $work();
            exit(0);
        } catch (Throwable) {
            exit(1);
        }
    }

    return $pid;
}
