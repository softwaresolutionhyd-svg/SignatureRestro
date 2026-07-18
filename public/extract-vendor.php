<?php
/**
 * Extract vendor.zip uploaded next to project root (or in public/).
 * Visit once: https://signature.softwaresolutions.pk/extract-vendor.php
 * Delete this file after success.
 */
header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(0);

$base = dirname(__DIR__);
$candidates = [
    $base.DIRECTORY_SEPARATOR.'vendor.zip',
    __DIR__.DIRECTORY_SEPARATOR.'vendor.zip',
];

$zipPath = null;
foreach ($candidates as $c) {
    if (is_file($c)) {
        $zipPath = $c;
        break;
    }
}

if ($zipPath === null) {
    http_response_code(404);
    echo "vendor.zip not found in project root or public/\n";
    exit;
}

echo "ZIP={$zipPath}\n";
echo "SIZE=".filesize($zipPath)." bytes\n";

if (! class_exists('ZipArchive')) {
    http_response_code(500);
    echo "ZipArchive extension missing on hosting.\n";
    exit;
}

$target = $base.DIRECTORY_SEPARATOR.'vendor';
if (! is_dir($target)) {
    mkdir($target, 0775, true);
}

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    http_response_code(500);
    echo "Cannot open zip.\n";
    exit;
}

echo "Extracting to {$target} ...\n";
$ok = $zip->extractTo($base);
$zip->close();

if (! $ok) {
    http_response_code(500);
    echo "Extract failed.\n";
    exit;
}

$autoload = $target.DIRECTORY_SEPARATOR.'autoload.php';
echo is_file($autoload) ? "OK vendor/autoload.php\n" : "MISS vendor/autoload.php\n";
echo "Done. Next: open /install.php to recreate .env (use existing DB, SYNC_ROLE=cloud).\n";
