<?php
/**
 * One-time hosting repair helper. Delete this file after site is healthy.
 * Visit: https://signature.softwaresolutions.pk/ensure-storage.php
 */
header('Content-Type: text/plain; charset=utf-8');

$base = dirname(__DIR__);
$dirs = [
    'storage/app',
    'storage/app/public',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache',
];

echo "BASE={$base}\n";
foreach ($dirs as $d) {
    $path = $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $d);
    if (! is_dir($path)) {
        @mkdir($path, 0775, true);
    }
    $ok = is_dir($path) && is_writable($path);
    echo ($ok ? 'OK  ' : 'FAIL')." {$d}\n";
}

$checks = [
    '.env' => $base.DIRECTORY_SEPARATOR.'.env',
    'vendor/autoload.php' => $base.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php',
    'public/index.php' => $base.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php',
    'bootstrap/app.php' => $base.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php',
];
echo "----\n";
foreach ($checks as $label => $path) {
    echo (is_file($path) ? 'OK  ' : 'MISS')." {$label}\n";
}

if (is_file($base.DIRECTORY_SEPARATOR.'.env')) {
    $env = file_get_contents($base.DIRECTORY_SEPARATOR.'.env');
    foreach (['APP_KEY=', 'DB_DATABASE=', 'SYNC_ROLE=', 'APP_URL='] as $needle) {
        if (preg_match('/^'.preg_quote($needle, '/').'(.*)$/m', $env, $m)) {
            $val = trim($m[1]);
            if (str_starts_with($needle, 'APP_KEY') || str_starts_with($needle, 'DB_')) {
                $val = $val === '' ? '(empty)' : '(set)';
            }
            echo "ENV {$needle}{$val}\n";
        } else {
            echo "ENV {$needle}(missing)\n";
        }
    }
}

echo "----\nPHP=".PHP_VERSION."\n";
echo "Done.\n";
