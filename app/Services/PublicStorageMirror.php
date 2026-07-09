<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Copies files from storage/app/public to public/storage so they are web-accessible
 * when the storage symlink is missing (common on Windows Laragon and some shared hosts).
 */
final class PublicStorageMirror
{
    public static function publish(string $relativePath): void
    {
        $relativePath = self::normalize($relativePath);
        if ($relativePath === '') {
            return;
        }

        $source = storage_path('app/public/'.$relativePath);
        if (! is_file($source)) {
            return;
        }

        $destination = public_path('storage/'.$relativePath);
        $directory = dirname($destination);
        if (! is_dir($directory)) {
            File::ensureDirectoryExists($directory, 0755, true);
        }

        copy($source, $destination);
    }

    public static function unpublish(?string $relativePath): void
    {
        $relativePath = self::normalize($relativePath ?? '');
        if ($relativePath === '') {
            return;
        }

        $destination = public_path('storage/'.$relativePath);
        if (is_file($destination)) {
            @unlink($destination);
        }
    }

    /** @return int Number of files mirrored */
    public static function publishAll(string $subDirectory = ''): int
    {
        $root = storage_path('app/public/'.trim(str_replace('\\', '/', $subDirectory), '/'));
        if (! is_dir($root)) {
            return 0;
        }

        $count = 0;
        $base = storage_path('app/public');

        foreach (File::allFiles($root) as $file) {
            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($base))), '/');
            if ($relative === '') {
                continue;
            }
            self::publish($relative);
            $count++;
        }

        return $count;
    }

    private static function normalize(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }
}
