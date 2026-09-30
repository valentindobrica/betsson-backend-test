<?php

declare(strict_types=1);

use App\Enums\WithdrawStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdraw_statuses', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary()->comment('Matches the backed value of the App\Enums\WithdrawStatus enum');
            $table->string('name');
        });

        // See the deposit_statuses migration for why this is seeded here.
        $statement = Schema::getConnection()->getPdo()->prepare(
            'INSERT INTO withdraw_statuses (id, name) VALUES (:id, :name)'
        );

        foreach (WithdrawStatus::cases() as $status) {
            $statement->execute(['id' => $status->value, 'name' => $status->label()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('withdraw_statuses');
    }
};
