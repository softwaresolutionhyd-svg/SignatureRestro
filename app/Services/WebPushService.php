<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushService
{
    private ?array $resolvedVapid = null;

    public function isConfigured(): bool
    {
        $keys = $this->vapidKeys();

        return ($keys['publicKey'] ?? '') !== '' && ($keys['privateKey'] ?? '') !== '';
    }

    public function publicKey(): string
    {
        return (string) ($this->vapidKeys()['publicKey'] ?? '');
    }

    /**
     * @return array{publicKey: string, privateKey: string, subject: string}
     */
    public function vapidKeys(): array
    {
        if ($this->resolvedVapid !== null) {
            return $this->resolvedVapid;
        }

        $public = trim((string) config('webpush.vapid.public_key', ''));
        $private = trim((string) config('webpush.vapid.private_key', ''));
        $subject = trim((string) config('webpush.vapid.subject', 'mailto:admin@softwaresolutions.pk'));
        if ($subject === '') {
            $subject = 'mailto:admin@softwaresolutions.pk';
        }

        if ($public === '' || $private === '') {
            $file = storage_path('app/vapid.json');
            if (is_file($file)) {
                $json = json_decode((string) file_get_contents($file), true);
                if (is_array($json)) {
                    $public = trim((string) ($json['publicKey'] ?? $json['public_key'] ?? $public));
                    $private = trim((string) ($json['privateKey'] ?? $json['private_key'] ?? $private));
                }
            }
        }

        if (($public === '' || $private === '') && class_exists(VAPID::class)) {
            try {
                $this->ensureOpenSslConf();
                $generated = null;
                try {
                    $generated = VAPID::createVapidKeys();
                } catch (Throwable) {
                    $generated = $this->createVapidKeysViaOpenSslCli();
                }
                if (! is_array($generated) || ($generated['publicKey'] ?? '') === '') {
                    $generated = $this->createVapidKeysViaOpenSslCli();
                }
                $public = (string) ($generated['publicKey'] ?? '');
                $private = (string) ($generated['privateKey'] ?? '');
                if ($public !== '' && $private !== '') {
                    $dir = storage_path('app');
                    if (! is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    file_put_contents(storage_path('app/vapid.json'), json_encode([
                        'publicKey' => $public,
                        'privateKey' => $private,
                        'subject' => $subject,
                    ], JSON_PRETTY_PRINT));
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $this->resolvedVapid = [
            'publicKey' => $public,
            'privateKey' => $private,
            'subject' => $subject,
        ];
    }

    /**
     * @return array{publicKey: string, privateKey: string}|null
     */
    protected function createVapidKeysViaOpenSslCli(): ?array
    {
        $candidates = [
            'C:\\Program Files\\Git\\usr\\bin\\openssl.exe',
            'C:\\Program Files (x86)\\Git\\usr\\bin\\openssl.exe',
            'openssl',
        ];
        $openssl = null;
        foreach ($candidates as $bin) {
            if ($bin === 'openssl') {
                $openssl = $bin;
                break;
            }
            if (is_file($bin)) {
                $openssl = $bin;
                break;
            }
        }
        if ($openssl === null) {
            return null;
        }

        $tmp = storage_path('app/_vapid_tmp_priv.pem');
        $cmd = ($openssl === 'openssl' ? 'openssl' : '"'.$openssl.'"')
            .' ecparam -name prime256v1 -genkey -noout -out '.escapeshellarg($tmp).' 2>&1';
        exec($cmd, $out, $code);
        if ($code !== 0 || ! is_file($tmp)) {
            return null;
        }

        $pem = (string) file_get_contents($tmp);
        @unlink($tmp);
        $key = openssl_pkey_get_private($pem);
        if ($key === false) {
            return null;
        }
        $details = openssl_pkey_get_details($key);
        if ($details === false || ! isset($details['ec']['x'], $details['ec']['y'], $details['ec']['d'])) {
            return null;
        }

        $public = chr(4)
            .str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT)
            .str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
        $private = str_pad($details['ec']['d'], 32, "\0", STR_PAD_LEFT);
        $b64url = static fn (string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

        return [
            'publicKey' => $b64url($public),
            'privateKey' => $b64url($private),
        ];
    }

    /** Laragon/Windows PHP often lacks OPENSSL_CONF — EC VAPID keys then fail. */
    protected function ensureOpenSslConf(): void
    {
        $existing = getenv('OPENSSL_CONF');
        if (is_string($existing) && $existing !== '' && is_file($existing)) {
            return;
        }

        $candidates = [
            'C:\\laragon\\bin\\apache\\httpd-2.4.54-win64-VS16\\conf\\openssl.cnf',
            'C:\\laragon\\bin\\apache\\httpd-2.4.53-win64-VS16\\conf\\openssl.cnf',
            storage_path('app/openssl.cnf'),
        ];
        foreach ($candidates as $path) {
            if (! is_file($path) && $path === storage_path('app/openssl.cnf')) {
                $dir = dirname($path);
                if (! is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                @file_put_contents($path, "HOME = .\nRANDFILE = .rnd\n");
            }
            if (is_file($path)) {
                putenv('OPENSSL_CONF='.$path);
                $_ENV['OPENSSL_CONF'] = $path;

                return;
            }
        }
    }

    /**
     * @param  Collection<int, User>|iterable<User>  $users
     * @param  array<string, mixed>  $payload
     */
    public function sendToUsers(iterable $users, array $payload): void
    {
        if (! $this->isConfigured() || ! Schema::hasTable('push_subscriptions')) {
            return;
        }

        $userIds = collect($users)->pluck('id')->filter()->unique()->values()->all();
        if ($userIds === []) {
            return;
        }

        $subs = PushSubscription::query()->whereIn('user_id', $userIds)->get();
        if ($subs->isEmpty()) {
            return;
        }

        $this->sendToSubscriptions($subs, $payload);
    }

    /**
     * Fan-out to every subscription for company users (hosting relay target).
     *
     * @param  array<string, mixed>  $payload
     */
    public function sendToCompany(?int $companyId, array $payload): int
    {
        if (! $this->isConfigured() || ! Schema::hasTable('push_subscriptions')) {
            return 0;
        }

        $query = User::query()->orderBy('id');
        if ($companyId !== null) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        $sent = 0;
        $query->chunkById(100, function ($users) use ($payload, &$sent) {
            $ids = $users->pluck('id')->all();
            $subs = PushSubscription::query()->whereIn('user_id', $ids)->get();
            if ($subs->isNotEmpty()) {
                $this->sendToSubscriptions($subs, $payload);
                $sent += $subs->count();
            }
        });

        return $sent;
    }

    /**
     * Cafe → hosting: so phones that installed the online PWA still get system alerts.
     *
     * @param  array<string, mixed>  $payload
     */
    public function relayToRemote(array $payload, ?int $companyId): void
    {
        if (! config('webpush.relay_to_remote', true)) {
            return;
        }

        if (! config('sync.enabled') || config('sync.role') !== 'local') {
            return;
        }

        $url = rtrim((string) config('sync.remote_url'), '/');
        $token = (string) config('sync.token');
        if ($url === '' || $token === '') {
            return;
        }

        $timeout = max(2, (int) config('webpush.relay_timeout_seconds', 4));

        $relayPayload = [
            'title' => (string) ($payload['title'] ?? 'Stair'),
            'message' => (string) ($payload['message'] ?? ''),
            'url' => $this->toRelativeUrl($payload['url'] ?? null),
            'action' => $payload['action'] ?? null,
            'level' => $payload['level'] ?? 'info',
            'icon' => $payload['icon'] ?? null,
            'order_id' => $payload['order_id'] ?? null,
            'order_no' => $payload['order_no'] ?? null,
        ];

        try {
            Http::timeout($timeout)
                ->connectTimeout(2)
                ->withToken($token)
                ->acceptJson()
                ->post($url.'/api/sync/push-notify', [
                    'company_id' => $companyId,
                    'payload' => $relayPayload,
                ]);
        } catch (Throwable $e) {
            Log::debug('webpush.relay_failed', ['error' => $e->getMessage()]);
        }
    }

    protected function toRelativeUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return '/';
        }
        if (str_starts_with($url, '/')) {
            return $url;
        }
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($path) || $path === '') {
            return '/';
        }

        return $query ? $path.'?'.$query : $path;
    }

    /**
     * @param  Collection<int, PushSubscription>  $subs
     * @param  array<string, mixed>  $payload
     */
    protected function sendToSubscriptions(Collection $subs, array $payload): void
    {
        $keys = $this->vapidKeys();
        if ($keys['publicKey'] === '' || $keys['privateKey'] === '') {
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => $keys['subject'],
                    'publicKey' => $keys['publicKey'],
                    'privateKey' => $keys['privateKey'],
                ],
            ]);
            $webPush->setReuseVAPIDHeaders(true);
        } catch (Throwable $e) {
            report($e);

            return;
        }

        $title = (string) ($payload['title'] ?? 'Stair');
        $body = (string) ($payload['message'] ?? '');
        $url = (string) ($payload['url'] ?? '/');
        $tag = 'stair-'.((string) ($payload['action'] ?? 'activity')).'-'.((string) ($payload['order_id'] ?? time()));

        $json = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'tag' => $tag,
            'icon' => '/icons/icon-192.png',
            'badge' => '/icons/icon-192.png',
            'level' => $payload['level'] ?? 'info',
        ], JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return;
        }

        foreach ($subs as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->public_key,
                    'authToken' => $sub->auth_token,
                    'contentEncoding' => $sub->content_encoding ?: 'aesgcm',
                ]);
                $webPush->queueNotification($subscription, $json);
            } catch (Throwable $e) {
                report($e);
            }
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            $endpoint = $report->getEndpoint();
            $code = $report->getResponse()?->getStatusCode();
            // Gone / expired subscription
            if (in_array($code, [404, 410], true) && is_string($endpoint) && $endpoint !== '') {
                PushSubscription::query()->where('endpoint', $endpoint)->delete();
            }
        }
    }
}
