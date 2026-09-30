<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('status_id');
            $table->foreign('status_id')->references('id')->on('deposit_statuses');
            $table->decimal('amount', 12, 2)->comment('Amount requested to be deposited');
            $table->decimal('bonus_amount', 12, 2)->default(0)->comment('Bonus credited once approved; 0 unless this was every 3rd approved deposit');
            $table->unsignedInteger('deposit_number')->nullable()->comment("This customer's Nth APPROVED deposit; set once approved, null until then");
            $table->decimal('balance_after', 12, 2)->nullable()->comment('Wallet balance snapshot once approved; null until then');
            $table->decimal('bonus_balance_after', 12, 2)->nullable()->comment('Wallet bonus balance snapshot once approved; null until then');
            $table->timestamp('approved_at')->nullable()->comment('When this deposit was approved or disapproved');
            $table->timestamps();

            $table->index(['customer_id', 'status_id']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
