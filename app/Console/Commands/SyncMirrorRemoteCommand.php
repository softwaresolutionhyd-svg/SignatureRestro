<?php

namespace App\Console\Commands;

use App\Services\Sync\CloudSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
        'expenses',
    ];

    public function handle(CloudSyncService $sync): int
    {
        if (! $sync->isLocalRole()) {
            $this->error('Run on cafe PC (SYNC_ROLE=local).');

            return self::FAILURE;
        }

        $url = rtrim((string) config('sync.remote_url'), '/');
        $token = (string) config('sync.token');
        if ($url === '' || $token === '') {
            $this->error('SYNC_REMOTE_URL / SYNC_TOKEN missing.');

            return self::FAILURE;
        }

        $opt = $this->option('tables');
        $tables = is_string($opt) && trim($opt) !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $opt))))
            : $this->defaultTables;

        $dry = (bool) $this->option('dry-run');
        $this->info($dry ? 'Dry-run (no remote deletes)…' : 'Mirroring cafe → hosting (delete remote-only rows)…');

        $okAll = true;
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                $this->warn("Skip missing local table: {$table}");
                continue;
            }

            $keepIds = DB::table($table)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
            $this->line(sprintf('%-24s keep_ids=%d', $table, count($keepIds)));

            if ($dry) {
                continue;
            }

            try {
                $response = Http::timeout(120)
                    ->connectTimeout(10)
                    ->withToken($token)
                    ->acceptJson()
                    ->post($url.'/api/sync/mirror', [
                        'table' => $table,
                        'keep_ids' => $keepIds,
                    ]);
            } catch (\Throwable $e) {
                $this->error("{$table}: ".$e->getMessage());
                $okAll = false;
                continue;
            }

            if (! $response->successful()) {
                $this->error("{$table}: HTTP ".$response->status().' '.$response->body());
                $okAll = false;
                continue;
            }

            $body = $response->json();
            $this->info('  → '.($body['message'] ?? json_encode($body)));
            if (! ($body['ok'] ?? false)) {
                $okAll = false;
            }
        }

        // Re-push local rows so hosting totals match after deletes.
        if (! $dry) {
            $this->info('Pushing local POS/purchase rows to hosting…');
            $n = 0;
            foreach (['pos_sessions', 'pos_orders', 'pos_order_items', 'pos_payments', 'purchase_orders', 'credit_ledger'] as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $sync, &$n) {
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
                $result = $sync->push(true);
                $pending = (int) ($result['pending'] ?? $sync->pendingCount());
                $this->line(sprintf(
                    'Push round %d: pushed=%d pending=%d %s',
                    $round,
                    (int) ($result['pushed'] ?? 0),
                    $pending,
                    $result['message'] ?? ''
                ));
                if (($result['pushed'] ?? 0) == 0 && str_contains((string) ($result['message'] ?? ''), 'already in progress')) {
                    sleep(2);
                }
            } while ($pending > 0 && $round < 40);
        }

        return $okAll ? self::SUCCESS : self::FAILURE;
    }
}
