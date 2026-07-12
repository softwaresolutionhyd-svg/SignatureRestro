<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\EmployeeStaffCategory;
use App\Services\Sync\CloudSyncService;
use App\Services\Sync\SyncOutboxRecorder;
use App\Services\Sync\SyncTargetSchemaService;
use Illuminate\Console\Command;

class SyncRepairCommand extends Command
{
    protected $signature = 'sync:repair
                            {--push : Push pending changes to hosting after schema repair}
                            {--queue-staff-categories : Re-queue all employees + staff categories for sync}';

    protected $description = 'Ensure sync target DB schema (mysql + tenant) and optionally push pending queue.';

    public function handle(SyncTargetSchemaService $schema, CloudSyncService $sync, SyncOutboxRecorder $recorder): int
    {
        $this->info('Ensuring payroll / loan / credit ledger schema on all DB connections…');
        $schema->ensureAll();
        $this->info('Schema check done.');

        if ($this->option('queue-staff-categories') && $sync->isLocalRole()) {
            $queued = 0;
            EmployeeStaffCategory::withoutGlobalScope('company')
                ->orderBy('id')
                ->each(function (EmployeeStaffCategory $category) use ($recorder, &$queued) {
                    $recorder->recordModel($category, 'upsert');
                    $queued++;
                });

            Employee::withoutGlobalScope('company')
                ->orderBy('id')
                ->each(function (Employee $employee) use ($recorder, &$queued) {
                    $recorder->recordModel($employee, 'upsert');
                    $queued++;
                });

            $this->info("Queued {$queued} staff category / employee row(s) for sync.");
        }

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
