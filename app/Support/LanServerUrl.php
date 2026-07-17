<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Setting;

class LanServerUrl
{
    public static function primaryCompanyId(): ?int
    {
        try {
            $cid = Company::query()->where('active', true)->orderBy('id')->value('id');

            return $cid !== null ? (int) $cid : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function setting(string $key, mixed $default = null, ?int $companyId = null): mixed
    {
        $cid = $companyId ?? current_company_id() ?? self::primaryCompanyId();
        if ($cid === null) {
            return $default;
        }

        try {
            $row = Setting::query()
                ->where('company_id', $cid)
                ->where('key', $key)
                ->value('value');

            return $row !== null && $row !== '' ? $row : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * Parse IP / host (and optional port) from user input.
     *
     * @return array{ip: string, port: ?int}
     */
    public static function parseInput(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['ip' => '', 'port' => null];
        }

        $raw = preg_replace('#^https?://#i', '', $raw) ?? $raw;
        $hostPart = explode('/', $raw)[0];
        $port = null;

        if (str_contains($hostPart, ':')) {
            [$hostPart, $portRaw] = explode(':', $hostPart, 2);
            $portNum = (int) $portRaw;
            if ($portNum > 0 && $portNum <= 65535) {
                $port = $portNum;
            }
        }

        return ['ip' => trim($hostPart), 'port' => $port];
    }

    public static function ip(?int $companyId = null): ?string
    {
        $ip = trim((string) self::setting('lan_server_ip', '', $companyId));
        if ($ip === '') {
            return null;
        }

        return self::parseInput($ip)['ip'] ?: null;
    }

    public static function port(?int $companyId = null): ?int
    {
        $fromSetting = trim((string) self::setting('lan_server_port', '', $companyId));
        if ($fromSetting !== '') {
            $p = (int) $fromSetting;

            return ($p > 0 && $p <= 65535) ? $p : null;
        }

        $ipRaw = trim((string) self::setting('lan_server_ip', '', $companyId));
        if ($ipRaw !== '' && str_contains($ipRaw, ':')) {
            return self::parseInput($ipRaw)['port'];
        }

        return null;
    }

    public static function baseUrl(?int $companyId = null): string
    {
        $ip = self::ip($companyId);
        if ($ip) {
            $port = self::port($companyId);
            $scheme = (string) self::setting('lan_server_https', '0', $companyId) === '1' ? 'https' : 'http';

            if ($port === null || ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
                return $scheme.'://'.$ip;
            }

            return $scheme.'://'.$ip.':'.$port;
        }

        if (! app()->runningInConsole() && request()) {
            return rtrim((string) request()->getSchemeAndHttpHost(), '/');
        }

        return rtrim((string) config('app.url', 'http://localhost'), '/');
    }

    public static function pathUrl(string $path, ?int $companyId = null): string
    {
        return self::baseUrl($companyId).'/'.ltrim($path, '/');
    }

    /** @return array<string, string> */
    public static function mobileLinks(?int $companyId = null): array
    {
        $base = self::baseUrl($companyId);

        return [
            'server_url' => $base,
            'order_taker_app' => $base,
            'order_taker_web' => self::pathUrl('order-taker', $companyId),
            'pos' => self::pathUrl('pos', $companyId),
            'kitchen' => self::pathUrl('kitchen', $companyId),
            'order_status' => self::pathUrl('order-status', $companyId),
        ];
    }

    /** @return array<string, mixed> */
    public static function apiPayload(?int $companyId = null): array
    {
        $cid = $companyId ?? current_company_id() ?? self::primaryCompanyId();

        return array_merge(self::mobileLinks($cid), [
            'company_name' => (string) self::setting('company_name', config('app.name'), $cid),
            'lan_server_ip' => self::ip($cid) ?? '',
            'lan_server_port' => self::port($cid) ?? '',
        ]);
    }
}
