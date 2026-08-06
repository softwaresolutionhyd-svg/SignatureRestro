<?php

/**
 * One-time: run pending migrations (device_tokens for FCM).
 * Open once: https://signature.softwaresolutions.pk/migrate-fcm.php
 * DELETE this file after use.
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);

if (! is_file($root.'/vendor/autoload.php')) {
    echo "vendor missing\n";
    exit(1);
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
echo Illuminate\Support\Facades\Artisan::output();
echo "\nDone. DELETE public/migrate-fcm.php now.\n";
