<?php

namespace App\Support;

use App\Models\User;

/** Plain usernames stored in users.email (no @domain required). */
final class LoginUsername
{
    /** Characters allowed in login username. */
    public const PATTERN = '/^[a-zA-Z0-9._-]{3,40}$/';

    /** Login form input (username or email — no spaces / employee names). */
    public const INPUT_PATTERN = '/^[a-zA-Z0-9._@-]{3,120}$/';

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    public static function loginInputRules(): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:120',
            'regex:'.self::INPUT_PATTERN,
        ];
    }

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

    /**
     * Resolve login input to exactly one user, or null if unknown/ambiguous.
     */
    public static function resolveUser(string $login): ?User
    {
        $raw = mb_strtolower(trim($login), 'UTF-8');
        if ($raw === '') {
            return null;
        }

        $candidates = array_values(array_unique(array_filter([
            $raw,
            self::toStoredValue($login),
        ])));

        $matches = User::query()
            ->where(function ($q) use ($candidates) {
                foreach ($candidates as $candidate) {
                    $q->orWhereRaw('LOWER(email) = ?', [$candidate]);
                }
            })
            ->get();

        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->count() > 1) {
            return null;
        }

        // Legacy emails stored as user@domain — allow username part only when unique.
        $username = self::toStoredValue($login);
        if ($username === '' || str_contains($raw, '@')) {
            return null;
        }

        $byPrefix = User::query()
            ->whereRaw("LOWER(SUBSTRING_INDEX(email, '@', 1)) = ?", [$username])
            ->get();

        return $byPrefix->count() === 1 ? $byPrefix->first() : null;
    }
}
