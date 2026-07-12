<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'sync enabled: '.(config('sync.enabled') ? 'yes' : 'no').PHP_EOL;
echo 'sync role: '.config('sync.role').PHP_EOL;
echo 'remote_url: '.config('sync.remote_url').PHP_EOL;

if (! Illuminate\Support\Facades\Schema::hasTable('sync_queue')) {
    echo 'no sync_queue table'.PHP_EOL;
    exit;
}

$pending = App\Models\SyncQueueItem::query()->whereNull('synced_at')->count();
echo 'pending total: '.$pending.PHP_EOL;

$byTable = App\Models\SyncQueueItem::query()
    ->whereNull('synced_at')
    ->selectRaw('table_name, count(*) as c')
    ->groupBy('table_name')
    ->orderByDesc('c')
    ->get();

foreach ($byTable as $row) {
    echo '  '.$row->table_name.': '.$row->c.PHP_EOL;
}

$errors = App\Models\SyncQueueItem::query()
    ->whereNull('synced_at')
    ->whereNotNull('last_error')
    ->count();
echo 'with errors: '.$errors.PHP_EOL;

$sample = App\Models\SyncQueueItem::query()
    ->whereNull('synced_at')
    ->orderByDesc('id')
    ->limit(3)
    ->get(['id','table_name','record_key','action','last_error','created_at']);

foreach ($sample as $s) {
    echo "sample #{$s->id} {$s->table_name} {$s->action} key={$s->record_key} err=".($s->last_error ?? '-').PHP_EOL;
}

$errs = App\Models\SyncQueueItem::query()
    ->whereNull('synced_at')
    ->whereNotNull('last_error')
    ->where('last_error', '!=', '')
    ->limit(5)
    ->pluck('last_error', 'id');
echo 'error samples:'.PHP_EOL;
foreach ($errs as $id => $msg) {
    echo "  #$id: $msg".PHP_EOL;
}

$status = app(App\Services\Sync\CloudSyncService::class)->status();
echo 'remote reachable: '.($status['online'] ? 'yes' : 'no').PHP_EOL;
echo 'status message: '.($status['message'] ?? '').PHP_EOL;
