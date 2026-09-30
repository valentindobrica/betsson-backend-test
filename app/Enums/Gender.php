<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Customer gender, as given on registration.
 *
 * Persisted on the `customers.gender` column by its backed string value.
 */
enum Gender: string
{
    /** Customer identifies as male. */
    case Male = 'male';

    /** Customer identifies as female. */
    case Female = 'female';

    /** Customer identifies as neither male nor female, or prefers not to say. */
    case Other = 'other';
}
