<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$items = App\Models\SyncQueueItem::query()
    ->where('table_name', 'inventory_departments')
    ->orderByDesc('id')
    ->limit(8)
    ->get(['id', 'record_key', 'synced_at', 'payload', 'last_error']);

foreach ($items as $i) {
    $p = $i->payload ?? [];
    echo '#'.$i->id.' key='.$i->record_key
        .' synced='.($i->synced_at ?: '-')
        .' ip='.($p['printer_ip'] ?? 'NULL')
        .' port='.($p['printer_port'] ?? 'NULL')
        .' err='.($i->last_error ?: '-')
        .PHP_EOL;
}

$sets = App\Models\SyncQueueItem::query()
    ->where('table_name', 'settings')
    ->orderByDesc('id')
    ->limit(20)
    ->get(['id', 'synced_at', 'payload']);

foreach ($sets as $i) {
    $p = $i->payload ?? [];
    $key = (string) ($p['key'] ?? '');
    if (! str_starts_with($key, 'cashier_printer')) {
        continue;
    }
    echo 'set #'.$i->id.' key='.$key.' val='.($p['value'] ?? '?').' synced='.($i->synced_at ?: '-').PHP_EOL;
}

$deps = App\Models\InventoryDepartment::withoutGlobalScope('company')
    ->orderBy('id')
    ->get(['id', 'name', 'printer_ip', 'printer_port']);

foreach ($deps as $d) {
    echo 'local dept '.$d->id.' '.$d->name.' => '.($d->printer_ip ?: '(none)').':'.($d->printer_port ?: '-').PHP_EOL;
}
