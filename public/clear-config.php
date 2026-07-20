<?php

/**
 * One-time: clear stale Laravel config cache after .env DB_HOST change.
 * Open once: https://signature.softwaresolutions.pk/clear-config.php
 * DELETE this file after use.
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);
$cacheDir = $root.'/bootstrap/cache';
$removed = [];

foreach (['config.php', 'routes-v7.php', 'events.php', 'services.php', 'packages.php'] as $file) {
    $path = $cacheDir.'/'.$file;
    if (is_file($path) && @unlink($path)) {
        $removed[] = $file;
    }
}

echo "Removed cache files: ".($removed === [] ? '(none found)' : implode(', ', $removed))."\n";

if (is_file($root.'/vendor/autoload.php')) {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    Illuminate\Support\Facades\Artisan::call('config:clear');
    Illuminate\Support\Facades\Artisan::call('cache:clear');
    echo trim(Illuminate\Support\Facades\Artisan::output())."\n";
    echo 'Laravel DB_HOST now: '.config('database.connections.mysql.host')."\n";
} else {
    echo "vendor missing — manual cache file delete only.\n";
}

echo "Done. DELETE public/clear-config.php now.\n";
