<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The existing indexes on both tables lead with customer_id, which doesn't
 * help the reporting query - it scans across all customers, filtering by
 * status and an approved_at/processed_at range instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposits', function (Blueprint $table): void {
            $table->index(['status_id', 'approved_at']);
        });

        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->index(['status_id', 'processed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table): void {
            $table->dropIndex(['status_id', 'approved_at']);
        });

        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->dropIndex(['status_id', 'processed_at']);
        });
    }
};
