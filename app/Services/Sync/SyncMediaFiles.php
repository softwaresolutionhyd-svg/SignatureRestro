<?php

namespace App\Services\Sync;

use App\Services\PublicStorageMirror;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Embeds / restores public disk files (product images, logos) inside sync payloads.
 */
final class SyncMediaFiles
{
    private const MAX_BYTES = 900_000; // keep sync JSON payloads under ~1MB per file

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function attachToPayload(string $table, array $payload): array
    {
        $path = self::pathFromPayload($table, $payload);
        if ($path === null) {
            return $payload;
        }

        $absolute = storage_path('app/public/'.$path);
        if (! is_file($absolute)) {
            $absolute = public_path('storage/'.$path);
        }
        if (! is_file($absolute)) {
            return $payload;
        }

        $size = filesize($absolute);
        if ($size === false || $size <= 0 || $size > self::MAX_BYTES) {
            return $payload;
        }

        $binary = @file_get_contents($absolute);
        if ($binary === false || $binary === '') {
            return $payload;
        }

        $payload['_sync_file_path'] = $path;
        $payload['_sync_file_b64'] = base64_encode($binary);
        $payload['_sync_file_sha1'] = sha1($binary);

        return $payload;
    }

    /**
     * Write embedded file from sync payload onto public disk + mirror.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>  payload without _sync_file_* keys
     */
    public static function restoreFromPayload(array $payload): array
    {
        $path = isset($payload['_sync_file_path']) ? self::normalizePath((string) $payload['_sync_file_path']) : null;
        $b64 = isset($payload['_sync_file_b64']) ? (string) $payload['_sync_file_b64'] : '';

        unset($payload['_sync_file_path'], $payload['_sync_file_b64'], $payload['_sync_file_sha1']);

        if ($path === null || $b64 === '') {
            return $payload;
        }

        $binary = base64_decode($b64, true);
        if ($binary === false || $binary === '') {
            return $payload;
        }

        Storage::disk('public')->put($path, $binary);
        PublicStorageMirror::publish($path);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function pathFromPayload(string $table, array $payload): ?string
    {
        if ($table === 'inventory_products') {
            return self::normalizePath((string) ($payload['image_path'] ?? ''));
        }

        if ($table === 'settings') {
            $key = (string) ($payload['key'] ?? '');
            if (! in_array($key, ['company_logo', 'logo', 'logo_path'], true)) {
                return null;
            }
            $value = (string) ($payload['value'] ?? '');
            // stored as relative public disk path like logos/xxx.jpg
            if (str_contains($value, 'logos/') || str_starts_with($value, 'products/')) {
                return self::normalizePath($value);
            }
        }

        return null;
    }

    private static function normalizePath(string $path): ?string
    {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }
        if (! str_starts_with($path, 'products/') && ! str_starts_with($path, 'logos/')) {
            return null;
        }

        return $path;
    }
}
