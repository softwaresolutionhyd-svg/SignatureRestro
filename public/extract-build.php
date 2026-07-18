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
if (! is_dir($target.'/assets')) {
    mkdir($target.'/assets', 0775, true);
}

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    http_response_code(500);
    echo "Cannot open zip\n";
    exit;
}

echo "ZIP_ENTRIES={$zip->numFiles}\n";
for ($i = 0; $i < min(10, $zip->numFiles); $i++) {
    echo 'ENTRY '.$zip->getNameIndex($i)."\n";
}

$ok = $zip->extractTo($target);
$zip->close();
if (! $ok) {
    http_response_code(500);
    echo "Extract failed\n";
    exit;
}

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
            $exists = is_file($path);
            if (str_ends_with($entry['file'], '.css')) {
                $cssOk = $exists;
            }
            if (str_ends_with($entry['file'], '.js')) {
                $jsOk = $exists;
            }
            echo ($exists ? 'OK  ' : 'MISS').' '.$entry['file'].' ('.($exists ? filesize($path) : 0).")\n";
        }
    }
}

echo is_file($manifest) ? "OK  manifest.json\n" : "MISS manifest.json\n";
echo ($cssOk && $jsOk) ? "DONE design assets restored\n" : "FAIL incomplete extract\n";
