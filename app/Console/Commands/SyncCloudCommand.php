<?php

namespace App\Console\Commands;

use App\Services\Sync\CloudSyncService;
use Illuminate\Console\Command;

class SyncCloudCommand extends Command
{
    protected $signature = 'sync:cloud
                            {--snapshot : Queue a full database snapshot before push}
                            {--pull : Also force pull from hosting (default when auto_pull on)}
                            {--status : Show sync status only}';

    protected $description = 'Push local changes to hosting and pull online credit book to cafe PC';

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

        $ok = true;

        $this->info('Pushing to hosting…');
        $push = $sync->push(true);
        $this->{$push['ok'] ? 'info' : 'error'}($push['message']);
        $this->line("Pushed: {$push['pushed']} | Pending: {$push['pending']}");
        $ok = $ok && ($push['ok'] || ($push['pending'] ?? 0) === 0);

        if ($this->option('pull') || config('sync.auto_pull', true)) {
            $this->info('Pulling credit book / sales from hosting…');
            $pull = $sync->pull(true);
            $this->{$pull['ok'] ? 'info' : 'error'}($pull['message']);
            $this->line("Pulled: {$pull['pulled']} | Failed: {$pull['failed']}");
            $ok = $ok && $pull['ok'];
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
