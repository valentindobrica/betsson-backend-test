<?php

declare(strict_types=1);

use App\Models\Customer;

arch()->preset()->php();
arch()->preset()->strict();
// Customer is a plain PDO-backed data object, not an Eloquent model (per project requirements).
arch()->preset()->laravel()->ignoring(Customer::class);
arch()->preset()->security()->ignoring([
    'assert',
]);

arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();

//
