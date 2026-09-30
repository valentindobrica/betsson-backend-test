<?php

declare(strict_types=1);

use Database\Seeders\CustomerSeeder;

it('seeds customers', function (): void {
    $this->seed(CustomerSeeder::class);

    $this->assertDatabaseCount('customers', 50);
});
