<?php

/**
 * Reassign POS tables into sitting areas as requested.
 * Run: php scripts/reassign_pos_tables_areas.php
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
    'General' => ['T1', 'T2'],
];

$areaSort = [
    'Ground Floor' => 10,
    'First Floor' => 20,
    'Lawn' => 30,
    'MJ' => 40,
    'General' => 50,
];

echo "=== Current state ===\n";
foreach (PosSittingArea::query()->orderBy('sort_order')->orderBy('name')->get() as $area) {
    $names = PosTable::query()->where('sitting_area_id', $area->id)->pluck('name')->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    echo $area->id.' | '.$area->name.' | '.count($names).' tables: '.implode(', ', $names)."\n";
}
echo "\n";

$moved = 0;
$created = 0;
$already = 0;
$missingTables = [];

foreach ($plan as $areaName => $tableNames) {
    $area = PosSittingArea::query()->firstOrCreate(
        ['name' => $areaName],
        [
            'sort_order' => $areaSort[$areaName] ?? 100,
            'active' => true,
        ]
    );

    if ((int) $area->sort_order !== (int) ($areaSort[$areaName] ?? $area->sort_order)) {
        $area->sort_order = $areaSort[$areaName];
        $area->save();
    }

    echo "AREA {$area->name} (id={$area->id})\n";

    foreach ($tableNames as $name) {
        $table = PosTable::query()->where('name', $name)->first();

        if (! $table) {
            $table = PosTable::query()->create([
                'sitting_area_id' => $area->id,
                'name' => $name,
                'active' => true,
            ]);
            $created++;
            echo "  + created {$name}\n";
            continue;
        }

        if ((int) $table->sitting_area_id === (int) $area->id) {
            $already++;
            echo "  = {$name} already here\n";
            continue;
        }

        $from = optional($table->sittingArea)->name ?? 'NULL';
        $table->sitting_area_id = $area->id;
        $table->active = true;
        $table->save();
        $moved++;
        echo "  > moved {$name} from {$from}\n";
    }
}

// Keep only T1/T2 in General — report leftovers still in General
$general = PosSittingArea::query()->where('name', 'General')->first();
if ($general) {
    $extras = PosTable::query()
        ->where('sitting_area_id', $general->id)
        ->whereNotIn('name', ['T1', 'T2'])
        ->orderBy('name')
        ->pluck('name')
        ->all();

    if ($extras) {
        echo "\nWARNING: General still has extra tables (not in plan):\n  ".implode(', ', $extras)."\n";
    }
}

echo "\n=== Final state ===\n";
foreach (PosSittingArea::query()->orderBy('sort_order')->orderBy('name')->get() as $area) {
    $names = PosTable::query()->where('sitting_area_id', $area->id)->pluck('name')->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    echo $area->name.' ('.count($names).'): '.implode(', ', $names)."\n";
}

echo "\nDone. moved={$moved} created={$created} already={$already}\n";
