<?php

namespace App\Console\Commands;

use App\Services\Sync\CloudSyncService;
use Illuminate\Console\Command;

class SyncCloudCommand extends Command
{
    protected $signature = 'sync:cloud
                            {--snapshot : Queue a full database snapshot before push}
                            {--pull : Also force pull from hosting (default when auto_pull on)}
                            {--reset-pull : Reset pull cursors (re-pull lookback window)}
                            {--status : Show sync status only}';

    protected $description = 'Push local changes to hosting and pull full hosting DB to cafe PC';

    public function handle(CloudSyncService $sync): int
    {
        if ($this->option('status')) {
            $this->table(
                ['Key', 'Value'],
                collect($sync->status())->map(fn ($v, $k) => [$k, is_bool($v) ? ($v ? 'yes' : 'no') : (string) $v])->all()
            );

            return self::SUCCESS;
        }

        if (! $sync->isLocalRole()) {
            $this->warn('SYNC_ENABLED must be true and SYNC_ROLE=local on the cafe PC.');

            return self::FAILURE;
        }

        if ($this->option('snapshot')) {
            $this->info('Queuing full snapshot…');
            $n = $sync->queueFullSnapshot();
            $this->info("Queued {$n} row(s).");
        }

        $this->info('Syncing both ways (local ↔ hosting)…');
        $this->line('Pull tables: '.count($sync->resolvePullTables()).' (full DB when SYNC_PULL_TABLES=*)');

        $result = $sync->syncBoth(true, (bool) $this->option('reset-pull'));
        $this->{$result['ok'] ? 'info' : 'error'}($result['message']);
        $this->line("Pushed: {$result['pushed']} | Pending: {$result['pending']} | Pulled: {$result['pulled']} | Failed: {$result['failed']}");

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
