<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class AppPasswordRules
{
    /** Strong password rules for staff / admin accounts. */
    public static function defaults(): Password
    {
        return Password::min(8)
            ->letters()
            ->mixedCase()
            ->numbers();
    }

    /** @return list<string|Password> */
    public static function optionalConfirmed(): array
    {
        return ['nullable', 'string', 'max:120', 'confirmed', self::defaults()];
    }

    /** @return list<string|Password> */
    public static function requiredConfirmed(): array
    {
        return ['required', 'string', 'max:120', 'confirmed', self::defaults()];
    }
}
