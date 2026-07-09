<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LoginTotpService
{
    private const CACHE_PREFIX = 'login_totp:';

    private const TTL_SECONDS = 600;

    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly TotpService $totp
    ) {}

    public function startChallenge(User $user, bool $remember): string
    {
        $token = Str::random(64);

        Cache::put(self::CACHE_PREFIX.$token, [
            'user_id' => $user->id,
            'remember' => $remember,
            'attempts' => 0,
        ], self::TTL_SECONDS);

        return $token;
    }

    /**
     * @return array{user: User, remember: bool}|null
     */
    public function verify(string $token, string $code): ?array
    {
        $cacheKey = self::CACHE_PREFIX.$token;
        $payload = Cache::get($cacheKey);

        if (! is_array($payload)) {
            return null;
        }

        $payload['attempts'] = ((int) ($payload['attempts'] ?? 0)) + 1;
        if ($payload['attempts'] > self::MAX_ATTEMPTS) {
            Cache::forget($cacheKey);

            return null;
        }

        Cache::put($cacheKey, $payload, self::TTL_SECONDS);

        $user = User::query()->find($payload['user_id'] ?? 0);
        if (! $user || ! $user->hasTwoFactorEnabled()) {
            Cache::forget($cacheKey);

            return null;
        }

        $accepted = $this->totp->verifyForUser($user, $code)
            || $this->totp->verifyRecoveryCode($user, $code);

        if (! $accepted) {
            return null;
        }

        Cache::forget($cacheKey);

        return [
            'user' => $user->fresh(),
            'remember' => (bool) ($payload['remember'] ?? false),
        ];
    }
}
