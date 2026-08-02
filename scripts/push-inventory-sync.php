<?php

use App\Models\InventoryProduct;
use App\Services\Sync\CloudSyncService;
use App\Services\Sync\SyncOutboxRecorder;
use Illuminate\Support\Facades\Cache;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$recorder = app(SyncOutboxRecorder::class);
$n = 0;
InventoryProduct::withoutGlobalScope('company')
    ->orderBy('id')
    ->chunkById(200, function ($rows) use ($recorder, &$n) {
        foreach ($rows as $row) {
            $recorder->recordModel($row, 'upsert');
            $n++;
        }
    });

echo "queued_products={$n}\n";

try {
    Cache::lock('sync:push_lock')->forceRelease();
} catch (Throwable $e) {
    // ignore
}

$sync = app(CloudSyncService::class);
$round = 0;
do {
    $round++;
    $result = $sync->push();
    $pending = (int) ($result['pending'] ?? $sync->pendingCount());
    echo "round {$round}: pushed=".((int) ($result['pushed'] ?? 0))." pending={$pending} msg=".($result['message'] ?? '')."\n";
    if (($result['pushed'] ?? 0) == 0 && str_contains((string) ($result['message'] ?? ''), 'already in progress')) {
        try {
            Cache::lock('sync:push_lock')->forceRelease();
        } catch (Throwable $e) {
        }
        sleep(1);
    }
} while ($pending > 0 && $round < 50);

echo "done pending=".$sync->pendingCount()."\n";
