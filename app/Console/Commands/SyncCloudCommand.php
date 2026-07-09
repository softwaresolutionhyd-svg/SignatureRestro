<?php

namespace App\Console\Commands;

use App\Services\Sync\CloudSyncService;
use Illuminate\Console\Command;

class SyncCloudCommand extends Command
{
    protected $signature = 'sync:cloud
                            {--snapshot : Queue a full database snapshot before push}
                            {--status : Show sync status only}';

    protected $description = 'Push pending local changes to hosting (online/offline sync)';

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

        $this->info('Pushing to hosting…');
        $result = $sync->push();
        $this->{$result['ok'] ? 'info' : 'error'}($result['message']);
        $this->line("Pushed: {$result['pushed']} | Pending: {$result['pending']}");

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
