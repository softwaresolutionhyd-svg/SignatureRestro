<?php

namespace App\Support;

use App\Models\User;

/** Plain usernames stored in users.email (no @domain required). */
final class LoginUsername
{
    /** Characters allowed in login username. */
    public const PATTERN = '/^[a-zA-Z0-9._-]{3,40}$/';

    /**
     * Normalize input for storage: plain username only (strip @domain if pasted).
     */
    public static function toStoredValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (str_contains($value, '@')) {
            $value = (string) strstr($value, '@', true);
        }

        return mb_strtolower($value);
    }

    /** Display value in UI (username without domain). */
    public static function display(?string $stored): ?string
    {
        if ($stored === null || trim($stored) === '') {
            return null;
        }

        $stored = trim($stored);
        if (str_contains($stored, '@')) {
            return (string) strstr($stored, '@', true);
        }

        return $stored;
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    public static function rules(?int $ignoreUserId = null): array
    {
        $rules = [
            'nullable',
            'string',
            'min:3',
            'max:40',
            'regex:'.self::PATTERN,
            function (string $attribute, mixed $value, \Closure $fail) use ($ignoreUserId): void {
                if ($value === null || trim((string) $value) === '') {
                    return;
                }

                $stored = self::toStoredValue((string) $value);
                $query = User::query()->where(function ($q) use ($stored) {
                    $q->where('email', $stored)
                        ->orWhere('email', 'like', $stored.'@%');
                });

                if ($ignoreUserId !== null) {
                    $query->where('id', '!=', $ignoreUserId);
                }

                if ($query->exists()) {
                    $fail('Ye username pehle se use ho raha hai.');
                }
            },
        ];

        return $rules;
    }
}
