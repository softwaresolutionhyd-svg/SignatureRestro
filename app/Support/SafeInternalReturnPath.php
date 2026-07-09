<?php

namespace App\Support;

/**
 * Normalizes post-login / post-edit "return" URLs to a safe same-site path + query string.
 */
final class SafeInternalReturnPath
{
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        if (preg_match('/[\r\n\0]/', $value)) {
            return null;
        }
        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return self::toAppRelativePath($value);
        }
        $base = rtrim((string) config('app.url'), '/');
        if ($base !== '' && str_starts_with($value, $base.'/')) {
            $parsed = parse_url($value);
            if (! is_array($parsed)) {
                return null;
            }
            $path = isset($parsed['path']) && (string) $parsed['path'] !== ''
                ? (string) $parsed['path']
                : '/';
            if (str_starts_with($path, '//')) {
                return null;
            }
            $query = isset($parsed['query']) && (string) $parsed['query'] !== ''
                ? '?'.$parsed['query']
                : '';

            return self::toAppRelativePath($path.$query);
        }
        $parsed = parse_url($value);
        if (is_array($parsed) && isset($parsed['host'], $parsed['path'])) {
            $path = (string) $parsed['path'];
            if ($path === '') {
                $path = '/';
            }
            if (str_starts_with($path, '//')) {
                return null;
            }
            if ((string) $parsed['host'] === request()->getHttpHost()) {
                $query = isset($parsed['query']) && (string) $parsed['query'] !== ''
                    ? '?'.$parsed['query']
                    : '';

                return self::toAppRelativePath($path.$query);
            }
        }

        return null;
    }

    /**
     * Convert absolute/subfolder path to app-relative path expected by redirect()->to().
     * Example: /Softwaresolution/public/manufacturing/boms/8/edit -> /manufacturing/boms/8/edit
     */
    private static function toAppRelativePath(string $pathWithQuery): string
    {
        $parsed = parse_url($pathWithQuery);
        if (! is_array($parsed)) {
            return $pathWithQuery;
        }
        $path = (string) ($parsed['path'] ?? '/');
        $query = isset($parsed['query']) && (string) $parsed['query'] !== ''
            ? '?'.$parsed['query']
            : '';

        $req = request();
        $baseCandidates = array_values(array_filter(array_unique([
            trim((string) $req->getBasePath(), '/'),
            trim((string) $req->getBaseUrl(), '/'),
            trim((string) preg_replace('#/index\.php$#i', '', (string) $req->getBaseUrl()), '/'),
        ])));

        foreach ($baseCandidates as $baseCandidate) {
            $basePath = '/'.$baseCandidate;
            $path = self::collapseDuplicateBasePrefix($path, $basePath);
            if ($path === $basePath) {
                $path = '/';
                break;
            }
            if (str_starts_with($path, $basePath.'/')) {
                $path = substr($path, strlen($basePath));
                break;
            }
            if (str_contains(strtolower($basePath), '/index.php')) {
                $withoutIndex = preg_replace('#/index\.php$#i', '', $basePath) ?: $basePath;
                $path = self::collapseDuplicateBasePrefix($path, $withoutIndex);
                if ($path === $withoutIndex) {
                    $path = '/';
                    break;
                }
                if (str_starts_with($path, $withoutIndex.'/')) {
                    $path = substr($path, strlen($withoutIndex));
                    break;
                }
            }
        }

        if ($path === '') {
            $path = '/';
        }
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        return $path.$query;
    }

    private static function collapseDuplicateBasePrefix(string $path, string $basePath): string
    {
        $basePath = rtrim($basePath, '/');
        if ($basePath === '') {
            return $path;
        }
        $double = $basePath.$basePath;
        if (str_starts_with($path, $double.'/')) {
            return substr($path, strlen($basePath));
        }
        if ($path === $double) {
            return $basePath;
        }

        return $path;
    }
}
