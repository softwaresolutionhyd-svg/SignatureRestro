<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

final class ManualSystemUpdateInstaller
{
    private const ALLOWED_DIRS = ['app', 'resources', 'routes', 'database', 'config', 'bootstrap', 'public'];

    private const ALLOWED_ROOT_FILES = ['composer.json', 'composer.lock', 'artisan'];

    /** Relative paths never copied from package (live data / deps). */
    private const SKIP_PREFIXES = [
        'vendor/',
        'node_modules/',
        'storage/',
        'public/storage/',
    ];

    /**
     * @return array{ok: bool, messages: list<string>}
     */
    public function installFromZip(UploadedFile $file): array
    {
        if (! class_exists(ZipArchive::class)) {
            return ['ok' => false, 'messages' => ['PHP extension zip (ZipArchive) zaroori hai.']];
        }

        $workBase = storage_path('app/manual-update-work');
        File::ensureDirectoryExists($workBase);
        $extractDir = $workBase.DIRECTORY_SEPARATOR.uniqid('upd_', true);
        File::ensureDirectoryExists($extractDir);

        try {
            $zip = new ZipArchive;
            if ($zip->open($file->getRealPath()) !== true) {
                return ['ok' => false, 'messages' => ['ZIP file khol nahi sakay.']];
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    continue;
                }
                $name = str_replace('\\', '/', (string) $stat['name']);
                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }
                if (str_contains($name, '..')) {
                    $zip->close();

                    return ['ok' => false, 'messages' => ['ZIP mein unsafe path — update reject.']];
                }
                $lower = strtolower($name);
                if (str_starts_with($lower, '.env') || str_contains($lower, '/.env')) {
                    $zip->close();

                    return ['ok' => false, 'messages' => ['ZIP mein .env allow nahi.']];
                }
            }

            if (! $zip->extractTo($extractDir)) {
                $zip->close();

                return ['ok' => false, 'messages' => ['Extract fail.']];
            }
            $zip->close();

            $contentRoot = $this->resolveContentRoot($extractDir);
            $this->mirrorIntoBase($contentRoot, base_path());

            $migrateOut = '';
            try {
                Artisan::call('migrate', ['--force' => true]);
                $migrateOut = trim(Artisan::output());
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'messages' => [
                        'Files copy ho chuki hain lekin migrate fail: '.$e->getMessage(),
                    ],
                ];
            }

            try {
                Artisan::call('optimize:clear');
            } catch (\Throwable) {
                // non-fatal
            }

            $messages = [
                'Allowed paths copy ho gaye (app, resources, routes, database, config, bootstrap, public, composer.*).',
                'vendor / storage / node_modules ZIP se apply nahi hote — live data safe.',
            ];
            if ($migrateOut !== '') {
                $messages[] = 'Migrate: '.$migrateOut;
            }
            $messages[] = 'Caches clear kar diye. Agar composer.json badla ho to server par `composer install --no-dev` chalayein.';

            return ['ok' => true, 'messages' => $messages];
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'messages' => [$e->getMessage()]];
        } finally {
            if (is_dir($extractDir)) {
                File::deleteDirectory($extractDir);
            }
        }
    }

    private function resolveContentRoot(string $extractDir): string
    {
        $items = array_values(array_filter(
            scandir($extractDir) ?: [],
            static fn (string $e): bool => $e !== '.' && $e !== '..'
        ));
        if (count($items) === 1 && is_dir($extractDir.DIRECTORY_SEPARATOR.$items[0])) {
            return $extractDir.DIRECTORY_SEPARATOR.$items[0];
        }

        return $extractDir;
    }

    private function mirrorIntoBase(string $contentRoot, string $base): void
    {
        $contentRoot = rtrim(realpath($contentRoot) ?: $contentRoot, DIRECTORY_SEPARATOR);
        $base = rtrim($base, DIRECTORY_SEPARATOR);
        $rootLen = strlen($contentRoot);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($contentRoot, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile()) {
                continue;
            }
            $full = $fileInfo->getPathname();
            $rel = substr($full, $rootLen);
            $rel = ltrim(str_replace('\\', '/', $rel), '/');
            if ($rel === '' || str_contains($rel, '..')) {
                throw new RuntimeException('Invalid relative path in package.');
            }
            if (! $this->isAllowedRelativePath($rel)) {
                continue;
            }

            $dest = $base.'/'.$rel;
            File::ensureDirectoryExists(dirname($dest));
            if (! @copy($full, $dest)) {
                throw new RuntimeException('Copy fail: '.$rel);
            }
        }
    }

    private function isAllowedRelativePath(string $rel): bool
    {
        $norm = strtolower(str_replace('\\', '/', $rel));
        foreach (self::SKIP_PREFIXES as $pre) {
            if (str_starts_with($norm, $pre)) {
                return false;
            }
        }

        $first = explode('/', $norm, 2)[0];
        if (in_array($first, array_map('strtolower', self::ALLOWED_DIRS), true)) {
            return true;
        }

        $basename = basename($norm);
        foreach (self::ALLOWED_ROOT_FILES as $allowed) {
            if ($norm === strtolower($allowed)) {
                return true;
            }
        }

        return false;
    }
}
