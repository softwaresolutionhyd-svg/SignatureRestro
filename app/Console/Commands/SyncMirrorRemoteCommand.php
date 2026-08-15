<?php

namespace App\Console\Commands;

use App\Services\Sync\CloudSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncMirrorRemoteCommand extends Command
{
    protected $signature = 'sync:mirror-remote
                            {--tables= : Comma list (default: POS + purchases + credit + expenses)}
                            {--dry-run : Only show local keep counts, do not call hosting}';

    protected $description = 'Make hosting DB match cafe for report tables (delete remote-only rows).';

    /** @var list<string> */
    private array $defaultTables = [
        'pos_order_items',
        'pos_payments',
        'pos_cash_movements',
        'pos_orders',
        'pos_sessions',
        'purchase_order_items',
        'purchase_orders',
        'credit_ledger',
        'expense_items',
        'expense_lines',
        'expenses',
    ];

    public function handle(CloudSyncService $sync): int
    {
        if (! $sync->isLocalRole()) {
            $this->error('Run on cafe PC (SYNC_ROLE=local).');

            return self::FAILURE;
        }

        $opt = $this->option('tables');
        $tables = is_string($opt) && trim($opt) !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $opt))))
            : $this->defaultTables;

        $dry = (bool) $this->option('dry-run');
        $this->info($dry ? 'Dry-run (no remote deletes)…' : 'Mirroring cafe → hosting (delete remote-only rows)…');

        if ($dry) {
            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    $this->warn("Skip missing local table: {$table}");
                    continue;
                }

                $count = (int) DB::table($table)->count();
                $this->line(sprintf('%-24s keep_ids=%d', $table, $count));
            }

            return self::SUCCESS;
        }

        $result = $sync->mirrorRemoteTables($tables);
        foreach ($result['tables'] as $row) {
            $this->line(sprintf(
                '%-24s %s',
                $row['table'],
                $row['message'] ?? ($row['ok'] ? 'ok' : 'failed')
            ));
        }
        $this->{$result['ok'] ? 'info' : 'error'}($result['message']);

        if (! $result['ok']) {
            return self::FAILURE;
        }

        $this->info('Pushing local POS/purchase rows to hosting…');
        $n = 0;
        foreach (['pos_sessions', 'pos_orders', 'pos_order_items', 'pos_payments', 'purchase_orders', 'credit_ledger'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, &$n) {
                foreach ($rows as $row) {
                    $attrs = (array) $row;
                    $key = (string) ($attrs['id'] ?? '');
                    if ($key === '') {
                        continue;
                    }
                    app(\App\Services\Sync\SyncOutboxRecorder::class)
                        ->recordUpsertRow($table, $key, $attrs);
                    $n++;
                }
            });
        }
        $this->line("Queued {$n} upsert(s).");

        $round = 0;
        do {
            $round++;
            $push = $sync->push(true);
            $pending = (int) ($push['pending'] ?? $sync->pendingCount());
            $this->line(sprintf(
                'Push round %d: pushed=%d pending=%d %s',
                $round,
                (int) ($push['pushed'] ?? 0),
                $pending,
                $push['message'] ?? ''
            ));
            if (($push['pushed'] ?? 0) == 0 && str_contains((string) ($push['message'] ?? ''), 'already in progress')) {
                sleep(2);
            }
        } while ($pending > 0 && $round < 40);

        return self::SUCCESS;
    }
}
