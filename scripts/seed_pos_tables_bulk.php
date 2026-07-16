<?php

/**
 * One-off: bulk-create POS tables under existing sitting areas.
 * Run: php artisan tinker < scripts/seed_pos_tables_bulk.php
 * Or:  php scripts/seed_pos_tables_bulk.php (via bootstrap)
 */

use App\Models\PosSittingArea;
use App\Models\PosTable;
use App\Support\PosTablesSchema;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

PosTablesSchema::ensure();

$plan = [
    'Ground Floor' => array_map(fn ($n) => 'GR'.$n, range(1, 20)),
    'First Floor' => array_map(fn ($n) => 'FT'.$n, range(21, 35)),
    'Lawn' => array_merge(
        array_map(fn ($n) => 'LG'.$n, range(36, 45)),
        array_map(fn ($n) => 'L'.$n, range(46, 55)),
    ),
    'MJ' => ['MJ888', 'MJ999'],
];

$created = 0;
$skipped = 0;

foreach ($plan as $areaName => $tables) {
    $area = PosSittingArea::query()->where('name', $areaName)->first();
    if (! $area) {
        // Fuzzy match (case / trim)
        $area = PosSittingArea::query()
            ->get()
            ->first(fn ($a) => strcasecmp(trim($a->name), $areaName) === 0);
    }
    if (! $area) {
        echo "MISSING AREA: {$areaName}\n";
        continue;
    }

    echo "AREA {$area->name} (id={$area->id})\n";
    foreach ($tables as $name) {
        $row = PosTable::query()->firstOrCreate(
            [
                'sitting_area_id' => $area->id,
                'name' => $name,
            ],
            ['active' => true]
        );
        if ($row->wasRecentlyCreated) {
            $created++;
            echo "  + {$name}\n";
        } else {
            $skipped++;
            echo "  = {$name} (exists)\n";
        }
    }
}

echo "\nDone. created={$created} skipped={$skipped}\n";
