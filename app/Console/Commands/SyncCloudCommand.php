<?php

namespace App\Console\Commands;

use App\Services\Sync\CloudSyncService;
use Illuminate\Console\Command;

class SyncCloudCommand extends Command
{
    protected $signature = 'sync:cloud
                            {--snapshot : Queue a full database snapshot before push}
                            {--pull : Also pull from hosting (requires SYNC_PULL_ENABLED)}
                            {--reset-pull : Reset pull cursors (re-pull lookback window)}
                            {--status : Show sync status only}';

    protected $description = 'Push cafe PC changes to hosting (one-way by default; pull optional)';

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

        $pullEnabled = (bool) config('sync.pull_enabled', false);
        $withPull = $pullEnabled && ($this->option('pull') || (bool) config('sync.auto_pull', false));

        $this->info($withPull ? 'Syncing both ways (local ↔ hosting)…' : 'Pushing cafe → hosting (one-way)…');
        if ($withPull) {
            $tables = $this->option('reset-pull')
                ? $sync->resolvePullTables()
                : $sync->tablesForPullCycle(false);
            $this->line('Pull this cycle: '.count($tables).' table(s)');
        }

        $result = $sync->syncBoth(true, (bool) $this->option('reset-pull'), $withPull);
        $this->{$result['ok'] ? 'info' : 'error'}($result['message']);
        $this->line("Pushed: {$result['pushed']} | Pending: {$result['pending']} | Pulled: {$result['pulled']} | Failed: {$result['failed']}");

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
