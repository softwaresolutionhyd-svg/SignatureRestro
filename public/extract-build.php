<?php
header("Content-Type: text/plain; charset=utf-8");
@set_time_limit(0);
$base = dirname(__DIR__);
$zipPath = __DIR__ . "/build-assets.zip";
if (!is_file($zipPath)) { http_response_code(404); echo "build-assets.zip missing\n"; exit; }
$target = __DIR__ . "/build";
if (!is_dir($target)) mkdir($target, 0775, true);
$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) { echo "open fail\n"; exit; }
$ok = $zip->extractTo($target);
$zip->close();
echo $ok && is_file($target."/manifest.json") ? "OK public/build/manifest.json\n" : "FAIL extract\n";
