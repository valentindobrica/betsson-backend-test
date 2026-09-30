<?php

declare(strict_types=1);

use App\Enums\DepositStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_statuses', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary()->comment('Matches the backed value of the App\Enums\DepositStatus enum');
            $table->string('name');
        });

        // Seeded here, not via a separate seeder: these rows are structural
        // (the DepositStatus enum's values only make sense if the matching
        // foreign key row exists), not user data, so the app is broken
        // without them the moment a migration runs. Written with raw PDO
        // rather than the query builder, consistent with the rest of the app.
        $statement = Schema::getConnection()->getPdo()->prepare(
            'INSERT INTO deposit_statuses (id, name) VALUES (:id, :name)'
        );

        foreach (DepositStatus::cases() as $status) {
            $statement->execute(['id' => $status->value, 'name' => $status->label()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_statuses');
    }
};
