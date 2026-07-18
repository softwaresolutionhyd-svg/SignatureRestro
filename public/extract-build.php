<?php
header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(0);

$zipPath = __DIR__.'/build-assets.zip';
$target = __DIR__.'/build';

if (! is_file($zipPath)) {
    http_response_code(404);
    echo "build-assets.zip missing\n";
    exit;
}

if (! class_exists('ZipArchive')) {
    http_response_code(500);
    echo "ZipArchive missing\n";
    exit;
}

if (! is_dir($target)) {
    mkdir($target, 0775, true);
}

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    http_response_code(500);
    echo "Cannot open zip\n";
    exit;
}

// Clear old hashed assets so stale hashes cannot 404
$assetsDir = $target.'/assets';
if (is_dir($assetsDir)) {
    foreach (glob($assetsDir.'/*') ?: [] as $f) {
        @unlink($f);
    }
}

$ok = $zip->extractTo($target);
$zip->close();

$manifest = $target.'/manifest.json';
$cssOk = false;
$jsOk = false;
if (is_file($manifest)) {
    $data = json_decode((string) file_get_contents($manifest), true);
    if (is_array($data)) {
        foreach ($data as $entry) {
            if (! is_array($entry) || empty($entry['file'])) {
                continue;
            }
            $path = $target.'/'.$entry['file'];
            if (str_ends_with($entry['file'], '.css')) {
                $cssOk = is_file($path);
                echo (is_file($path) ? 'OK  ' : 'MISS').' '.$entry['file'].' ('.(is_file($path) ? filesize($path) : 0).")\n";
            }
            if (str_ends_with($entry['file'], '.js')) {
                $jsOk = is_file($path);
                echo (is_file($path) ? 'OK  ' : 'MISS').' '.$entry['file'].' ('.(is_file($path) ? filesize($path) : 0).")\n";
            }
        }
    }
}

echo is_file($manifest) ? "OK  manifest.json\n" : "MISS manifest.json\n";
echo ($ok && $cssOk && $jsOk) ? "DONE design assets restored\n" : "FAIL incomplete extract\n";
