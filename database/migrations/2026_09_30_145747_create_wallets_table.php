<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->foreignId('customer_id')
                ->primary()
                ->comment('One wallet per customer; customer_id doubles as the primary key')
                ->constrained()
                ->cascadeOnDelete();
            $table->decimal('balance', 12, 2)->default(0)->comment('Real, withdrawable money balance');
            $table->decimal('bonus_balance', 12, 2)->default(0)->comment('Bonus money balance; can never be withdrawn');
            $table->unsignedInteger('approved_deposit_count')->default(0)->comment(
                "Count of this customer's approved deposits, kept on the wallet (rather than derived via "
                .'COUNT() on the deposits table) so it is read/written under the same row lock as the '
                .'balance, immune to snapshot-read staleness under concurrent approvals'
            );
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
