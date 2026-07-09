<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class LoginRateLimitService
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 600;

    public function keyForLogin(Request $request): string
    {
        $login = mb_strtolower(trim((string) $request->input('login', '')));

        return 'login|'.$request->ip().'|'.$login;
    }

    public function keyForIp(string $prefix, Request $request): string
    {
        return $prefix.'|'.$request->ip();
    }

    public function tooManyAttempts(string $key): bool
    {
        return RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS);
    }

    public function hit(string $key): void
    {
        RateLimiter::hit($key, self::DECAY_SECONDS);
    }

    public function clear(string $key): void
    {
        RateLimiter::clear($key);
    }

    public function availableIn(string $key): int
    {
        return RateLimiter::availableIn($key);
    }

    public function remaining(string $key): int
    {
        return RateLimiter::remaining($key, self::MAX_ATTEMPTS);
    }

    public function lockoutMessage(string $key): string
    {
        $minutes = max(1, (int) ceil($this->availableIn($key) / 60));

        return "5 galat attempts ho chuke hain. {$minutes} minute baad dubara try karein.";
    }

    public function failedLoginMessage(string $key): string
    {
        $remaining = $this->remaining($key);

        if ($remaining <= 0) {
            return $this->lockoutMessage($key);
        }

        return "Galat username ya password. Baqi attempts: {$remaining}.";
    }

    public function failedCodeMessage(string $key, string $label = 'code'): string
    {
        $remaining = $this->remaining($key);

        if ($remaining <= 0) {
            return $this->lockoutMessage($key);
        }

        return "Galat {$label}. Baqi attempts: {$remaining}.";
    }
}
