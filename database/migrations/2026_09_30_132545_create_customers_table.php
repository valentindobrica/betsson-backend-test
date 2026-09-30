<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('gender', 20)->comment('Backed value of the App\Enums\Gender enum (male, female, other)');
            $table->string('first_name')->comment('Customer first name, as given on registration');
            $table->string('last_name')->comment('Customer last name, as given on registration');
            $table->char('country', 2)->index()->comment('ISO 3166-1 alpha-2 country code, always uppercase');
            $table->string('email')->unique()->comment('Unique login/contact email, always stored lowercase');
            $table->unsignedTinyInteger('bonus_percentage')->comment('Random percentage (5-20) assigned on registration, used to calculate deposit bonuses');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
