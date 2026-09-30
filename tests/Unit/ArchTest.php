<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Deposit;
use App\Models\Wallet;

arch()->preset()->php();
arch()->preset()->strict();
// These are plain PDO-backed data objects, not Eloquent models (per project requirements).
arch()->preset()->laravel()->ignoring([
    Customer::class,
    Wallet::class,
    Deposit::class,
]);
arch()->preset()->security()->ignoring([
    'assert',
]);

arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();

//
