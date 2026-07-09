<?php

/**
 * Build dist/Stair-install-YYYYMMDD.zip for distribution (includes vendor if present).
 *
 * Run from project root: php scripts/make-release-zip.php
 */

declare(strict_types=1);

$root = realpath(dirname(__DIR__));
if ($root === false) {
    fwrite(STDERR, "Could not resolve project root.\n");
    exit(1);
}

if (! class_exists(ZipArchive::class)) {
    fwrite(STDERR, "PHP zip extension required.\n");
    exit(1);
}

$dist = $root.DIRECTORY_SEPARATOR.'dist';
if (! is_dir($dist) && ! mkdir($dist, 0755, true) && ! is_dir($dist)) {
    fwrite(STDERR, "Could not create dist/\n");
    exit(1);
}

$zipName = 'Stair-install-'.date('Ymd').'.zip';
$zipPath = $dist.DIRECTORY_SEPARATOR.$zipName;

if (is_file($zipPath)) {
    unlink($zipPath);
}

$excludePathParts = ['.git', 'node_modules', 'dist', '.idea', '.fleet', '.vscode'];

$shouldSkip = static function (string $fullPath) use ($root, $excludePathParts): bool {
    $rel = str_replace('\\', '/', substr($fullPath, strlen($root) + 1));
    if ($rel === false || $rel === '') {
        return false;
    }
    $parts = explode('/', $rel);
    foreach ($excludePathParts as $ex) {
        if (in_array($ex, $parts, true)) {
            return true;
        }
    }
    if (str_starts_with($rel, 'storage/logs/') && $rel !== 'storage/logs/.gitignore') {
        return true;
    }
    if (preg_match('#^storage/framework/(cache/data|sessions|views)/#', $rel)) {
        return basename($rel) === '.gitignore' ? false : true;
    }
    if ($rel === '.env' || $rel === '.env.backup' || $rel === '.env.production') {
        return true;
    }

    return false;
};

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Could not open zip for writing.\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
    RecursiveIteratorIterator::SELF_FIRST
);

$count = 0;
/** @var SplFileInfo $info */
foreach ($iterator as $info) {
    if (! $info->isFile()) {
        continue;
    }
    $path = $info->getPathname();
    if ($shouldSkip($path)) {
        continue;
    }
    $local = str_replace('\\', '/', substr($path, strlen($root) + 1));
    $zip->addFile($path, $local);
    $count++;
}

$zip->close();

$vendor = $root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
if (! is_file($vendor)) {
    fwrite(STDERR, "WARNING: vendor/ missing — zip mein composer packages nahi. Pehle `composer install --no-dev` chalayein.\n");
}

echo "Created {$zipPath} ({$count} files).\n";
echo "Upload extract karein, web root `public/` par point karein, phir /install.php kholein.\n";
