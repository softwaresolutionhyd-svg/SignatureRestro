<?php

/**
 * Database tables pehle se maujood hain lekin migrations table incomplete hai.
 * Is script se applied migrations mark hoti hain, phir sirf missing tables create hote hain.
 *
 * Run: php scripts/sync-migration-history.php
 * Then: php artisan migrate
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$migrationsPath = database_path('migrations');
$files = collect(glob($migrationsPath . '/*.php'))
    ->map(fn ($f) => pathinfo($f, PATHINFO_FILENAME))
    ->sort()
    ->values();

$ran = DB::table('migrations')->pluck('migration')->all();
$batch = (int) DB::table('migrations')->max('batch') + 1;

/** Migrations jin ke tables abhi DB mein nahi — inhe actually run karna hai */
$mustRun = [
    '2026_04_02_120408_create_notifications_table',
    '2026_04_03_220000_create_inventory_uom_library_tables',
    '2026_05_18_210000_create_room_booking_guest_room_table',
    '2026_06_09_100000_create_room_booking_members_table',
    '2026_06_11_100000_add_vehicles_to_room_bookings',
];

$toInsert = [];

foreach ($files as $name) {
    if (in_array($name, $ran, true)) {
        continue;
    }
    if (in_array($name, $mustRun, true)) {
        echo "[RUN LATER] $name\n";
        continue;
    }

    $file = $migrationsPath . '/' . $name . '.php';
    $content = file_get_contents($file);
    $skip = true;

    if (preg_match_all("/Schema::create\(['\"]([^'\"]+)['\"]/", $content, $creates)) {
        foreach ($creates[1] as $table) {
            if (! Schema::hasTable($table)) {
                $skip = false;
                echo "[NEEDS TABLE] $name -> $table\n";
                break;
            }
        }
    }

    if ($skip) {
        $toInsert[] = $name;
    }
}

if ($toInsert === []) {
    echo "No migrations to mark as run.\n";
} else {
    $rows = array_map(fn ($m) => ['migration' => $m, 'batch' => $batch], $toInsert);
    foreach (array_chunk($rows, 50) as $chunk) {
        DB::table('migrations')->insert($chunk);
    }
    echo 'Marked ' . count($toInsert) . " migrations as run (batch $batch).\n";
}

echo "\nAb chalao: php artisan migrate\n";
