<?php
/**
 * Extract public storage media (products/logos) after FTP upload of storage-public.zip
 * Visit: /extract-storage.php
 */
header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(0);

$base = dirname(__DIR__);
$zipPath = $base.'/storage-public.zip';
if (! is_file($zipPath)) {
    $zipPath = __DIR__.'/storage-public.zip';
}
if (! is_file($zipPath)) {
    http_response_code(404);
    echo "storage-public.zip missing\n";
    exit;
}

if (! class_exists('ZipArchive')) {
    http_response_code(500);
    echo "ZipArchive missing\n";
    exit;
}

$targets = [
    $base.'/storage/app/public',
    $base.'/public/storage',
];

foreach ($targets as $target) {
    if (! is_dir($target)) {
        mkdir($target, 0775, true);
    }
}

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    http_response_code(500);
    echo "Cannot open zip\n";
    exit;
}

echo "ZIP_ENTRIES={$zip->numFiles} SIZE=".filesize($zipPath)."\n";

$ok1 = $zip->extractTo($targets[0]);
$ok2 = $zip->extractTo($targets[1]);
$zip->close();

$sample = $targets[1].'/products';
$count = is_dir($sample) ? count(glob($sample.'/*') ?: []) : 0;
echo ($ok1 ? 'OK' : 'FAIL')." storage/app/public\n";
echo ($ok2 ? 'OK' : 'FAIL')." public/storage\n";
echo "PRODUCT_FILES={$count}\n";
echo ($ok1 && $ok2 && $count > 0) ? "DONE product images restored\n" : "FAIL incomplete\n";
