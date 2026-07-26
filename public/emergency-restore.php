<?php

/**
 * Emergency full-site restore for empty hosting folders.
 * Upload app-restore.zip to project root, then open:
 *   https://signature.softwaresolutions.pk/emergency-restore.php?key=YOUR_DEPLOY_KEY
 *
 * DELETE this file after the site is healthy.
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(0);
@ini_set('memory_limit', '1024M');

$base = dirname(__DIR__);
$expectedKey = getenv('DEPLOY_KEY') ?: '';

// Allow key from query/header OR a sibling .deploy-key file uploaded with the zip.
$keyFile = $base.DIRECTORY_SEPARATOR.'.deploy-key';
if ($expectedKey === '' && is_file($keyFile)) {
    $expectedKey = trim((string) file_get_contents($keyFile));
}

$given = (string) ($_GET['key'] ?? $_SERVER['HTTP_X_DEPLOY_KEY'] ?? '');
if ($expectedKey === '' || ! hash_equals($expectedKey, $given)) {
    // Also accept if .deploy-key matches POST body for first bootstrap.
    http_response_code(403);
    echo "Forbidden: invalid deploy key\n";
    echo "Pass ?key=... matching hosting .deploy-key / DEPLOY_KEY\n";
    exit;
}

$zipPath = $base.DIRECTORY_SEPARATOR.'app-restore.zip';
if (! is_file($zipPath)) {
    $zipPath = __DIR__.DIRECTORY_SEPARATOR.'app-restore.zip';
}
if (! is_file($zipPath)) {
    http_response_code(404);
    echo "app-restore.zip not found in project root or public/\n";
    exit;
}

if (! class_exists('ZipArchive')) {
    http_response_code(500);
    echo "ZipArchive extension missing on hosting\n";
    exit;
}

echo "BASE={$base}\n";
echo "ZIP={$zipPath} (".filesize($zipPath)." bytes)\n";

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    http_response_code(500);
    echo "Cannot open zip\n";
    exit;
}

$ok = $zip->extractTo($base);
$count = $zip->numFiles;
$zip->close();

echo $ok ? "Extract OK ({$count} entries)\n" : "Extract FAILED\n";

$checks = [
    'public/index.php',
    'public/.htaccess',
    'vendor/autoload.php',
    'artisan',
    'bootstrap/app.php',
];
$allOk = true;
foreach ($checks as $rel) {
    $path = $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $exists = is_file($path);
    echo ($exists ? 'OK  ' : 'MISS').' '.$rel."\n";
    $allOk = $allOk && $exists;
}

$envOk = is_file($base.DIRECTORY_SEPARATOR.'.env');
echo ($envOk ? 'OK  ' : 'MISS')." .env\n";

// Ensure storage dirs exist
$dirs = [
    'storage/app',
    'storage/app/public',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache',
];
foreach ($dirs as $d) {
    $path = $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $d);
    if (! is_dir($path)) {
        @mkdir($path, 0775, true);
    }
}

echo $allOk ? "DONE site files restored\n" : "DONE with missing files\n";
if (! $envOk) {
    echo "NEXT: create public_html/signature/.env (use restore-env.php if available)\n";
}
