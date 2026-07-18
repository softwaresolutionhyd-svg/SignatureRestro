<?php
header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(0);

$base = dirname(__DIR__);
$zipPath = $base.'/vendor.zip';
if (! is_file($zipPath)) {
    $zipPath = __DIR__.'/vendor.zip';
}
if (! is_file($zipPath)) {
    http_response_code(404);
    echo "vendor.zip not found\n";
    exit;
}

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    http_response_code(500);
    echo "Cannot open zip\n";
    exit;
}
$ok = $zip->extractTo($base);
$zip->close();
$autoload = $base.'/vendor/autoload.php';
echo is_file($autoload) ? "OK vendor/autoload.php\n" : "MISS vendor/autoload.php\n";
echo $ok ? "Done.\n" : "Extract failed\n";
