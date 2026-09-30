<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('status_id');
            $table->foreign('status_id')->references('id')->on('withdraw_statuses');
            $table->decimal('amount', 12, 2)->comment('Amount withdrawn from the real balance, reserved immediately on request');
            $table->boolean('pre_approved')->default(false)->comment('Set true by the scheduled command before dispatching the processing job');
            $table->decimal('balance_after', 12, 2)->comment('Wallet balance snapshot immediately after the funds were reserved');
            $table->timestamp('processed_at')->nullable()->comment('When the processing job resolved this withdrawal');
            $table->timestamps();

            $table->index(['customer_id', 'status_id']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['status_id', 'pre_approved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
