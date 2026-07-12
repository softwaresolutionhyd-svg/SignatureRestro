<?php

namespace App\Console\Commands;

use App\Services\Sync\CloudSyncService;
use App\Services\Sync\SyncTargetSchemaService;
use Illuminate\Console\Command;

class SyncRepairCommand extends Command
{
    protected $signature = 'sync:repair {--push : Push pending changes to hosting after schema repair}';

    protected $description = 'Ensure sync target DB schema (mysql + tenant) and optionally push pending queue.';

    public function handle(SyncTargetSchemaService $schema, CloudSyncService $sync): int
    {
        $this->info('Ensuring payroll / loan / credit ledger schema on all DB connections…');
        $schema->ensureAll();
        $this->info('Schema check done.');

        if (! $this->option('push')) {
            $this->comment('Run with --push to upload pending sync queue to hosting.');

            return self::SUCCESS;
        }

        if (! $sync->isLocalRole()) {
            $this->warn('Push only runs when SYNC_ROLE=local.');

            return self::SUCCESS;
        }

        $round = 0;
        do {
            $round++;
            $before = $sync->pendingCount();
            $result = $sync->push();
            $after = (int) ($result['pending'] ?? $sync->pendingCount());
            $pushed = (int) ($result['pushed'] ?? 0);

            $this->line(sprintf(
                'Round %d: pushed %d, pending %d — %s',
                $round,
                $pushed,
                $after,
                $result['message'] ?? ''
            ));

            if ($pushed === 0 || $after >= $before) {
                break;
            }
        } while ($round < 20 && $after > 0);

        $remaining = $sync->pendingCount();
        if ($remaining > 0) {
            $this->warn("{$remaining} item(s) still pending — hosting par latest code deploy karein, phir dubara sync:repair --push.");
        } else {
            $this->info('Sync queue clear.');
        }

        return self::SUCCESS;
    }
}
