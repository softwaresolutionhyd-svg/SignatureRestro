<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Firebase Cloud Messaging (HTTP v1) for Stair admin / POS alerts.
 */
class FcmService
{
    private ?array $serviceAccount = null;

    public function isConfigured(): bool
    {
        if (! config('fcm.enabled', true)) {
            return false;
        }

        $sa = $this->serviceAccount();

        return is_array($sa)
            && ($sa['client_email'] ?? '') !== ''
            && ($sa['private_key'] ?? '') !== ''
            && $this->projectId() !== '';
    }

    public function projectId(): string
    {
        $fromEnv = trim((string) config('fcm.project_id', ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $sa = $this->serviceAccount();

        return trim((string) ($sa['project_id'] ?? ''));
    }

    /**
     * @param  Collection<int, User>|iterable<User>  $users
     * @param  array<string, mixed>  $payload
     */
    public function sendToUsers(iterable $users, array $payload, string $app = 'admin'): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        if (! Schema::connection('mysql')->hasTable('device_tokens')) {
            return;
        }

        $userIds = collect($users)->pluck('id')->filter()->unique()->values()->all();
        if ($userIds === []) {
            return;
        }

        $tokens = DeviceToken::query()
            ->whereIn('user_id', $userIds)
            ->where('app', $app)
            ->pluck('token')
            ->filter()
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return;
        }

        foreach ($tokens->chunk(100) as $chunk) {
            foreach ($chunk as $token) {
                $this->sendToToken((string) $token, $payload);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendToToken(string $token, array $payload): bool
    {
        $token = trim($token);
        if ($token === '' || ! $this->isConfigured()) {
            return false;
        }

        try {
            $accessToken = $this->accessToken();
            if ($accessToken === '') {
                Log::warning('FCM: empty access token');

                return false;
            }

            $title = (string) ($payload['title'] ?? 'Stair');
            $body = (string) ($payload['message'] ?? $payload['body'] ?? '');
            $action = (string) ($payload['action'] ?? '');
            $orderId = $payload['order_id'] ?? null;
            $orderNo = (string) ($payload['order_no'] ?? '');
            $level = (string) ($payload['level'] ?? 'info');
            $screen = $this->screenForAction($action);
            $channelId = (string) config('fcm.android_channel_id', 'stair_pos_orders');

            // data values must be strings for FCM
            $data = [
                'title' => $title,
                'body' => $body,
                'action' => $action,
                'level' => $level,
                'order_id' => $orderId !== null ? (string) $orderId : '',
                'order_no' => $orderNo,
                'screen' => $screen,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ];

            $message = [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => $channelId,
                        'sound' => 'default',
                        'default_vibrate_timings' => true,
                        'notification_priority' => 'PRIORITY_MAX',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ],
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                ],
            ];

            $url = sprintf(
                'https://fcm.googleapis.com/v1/projects/%s/messages:send',
                rawurlencode($this->projectId())
            );

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(12)
                ->post($url, ['message' => $message]);

            if ($response->successful()) {
                DeviceToken::query()
                    ->where('token', $token)
                    ->update(['last_used_at' => now()]);

                return true;
            }

            $errorCode = (string) data_get($response->json(), 'error.details.0.errorCode', '');
            $status = (string) data_get($response->json(), 'error.status', '');
            $msg = (string) data_get($response->json(), 'error.message', $response->body());

            Log::warning('FCM send failed', [
                'status' => $response->status(),
                'error_code' => $errorCode,
                'fcm_status' => $status,
                'message' => $msg,
                'token_suffix' => substr($token, -12),
            ]);

            if (config('fcm.prune_invalid_tokens', true) && $this->shouldPrune($response->status(), $errorCode, $status, $msg)) {
                DeviceToken::query()->where('token', $token)->delete();
            }

            return false;
        } catch (Throwable $e) {
            report($e);
            Log::error('FCM send exception: '.$e->getMessage());

            return false;
        }
    }

    private function shouldPrune(int $httpStatus, string $errorCode, string $status, string $message): bool
    {
        $hay = strtoupper($errorCode.' '.$status.' '.$message);

        return $httpStatus === 404
            || str_contains($hay, 'UNREGISTERED')
            || str_contains($hay, 'NOT_FOUND')
            || str_contains($hay, 'INVALID_ARGUMENT');
    }

    private function screenForAction(string $action): string
    {
        return match (true) {
            str_contains($action, 'kitchen_void'), str_contains($action, 'cancel') => 'kitchen_voids',
            str_contains($action, 'paid'), str_contains($action, 'refund'), str_contains($action, 'reopen') => 'bills_paid',
            str_contains($action, 'placed'), str_contains($action, 'updated'), str_contains($action, 'deleted') => 'bills_pending',
            default => 'bills',
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serviceAccount(): ?array
    {
        if ($this->serviceAccount !== null) {
            return $this->serviceAccount;
        }

        $path = (string) config('fcm.credentials', '');
        if ($path === '' || ! is_file($path)) {
            return $this->serviceAccount = null;
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json)) {
            return $this->serviceAccount = null;
        }

        return $this->serviceAccount = $json;
    }

    private function accessToken(): string
    {
        $project = $this->projectId();
        $cacheKey = 'fcm_oauth_access_token_'.$project;

        return (string) Cache::remember($cacheKey, 3300, function () {
            $sa = $this->serviceAccount();
            if (! is_array($sa)) {
                return '';
            }

            $now = time();
            $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $claims = $this->base64UrlEncode(json_encode([
                'iss' => (string) $sa['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR));

            $unsigned = $header.'.'.$claims;
            $privateKey = openssl_pkey_get_private((string) $sa['private_key']);
            if ($privateKey === false) {
                Log::error('FCM: invalid service account private key');

                return '';
            }

            $signature = '';
            $ok = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
            if (! $ok) {
                Log::error('FCM: openssl_sign failed');

                return '';
            }

            $jwt = $unsigned.'.'.$this->base64UrlEncode($signature);

            $response = Http::asForm()
                ->timeout(15)
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

            if (! $response->successful()) {
                Log::error('FCM OAuth failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return '';
            }

            return (string) ($response->json('access_token') ?? '');
        });
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
