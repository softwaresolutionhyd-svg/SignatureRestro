<?php

namespace App\Services\Sync;

use App\Models\InventoryUnit;
use App\Models\SyncQueueItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CloudSyncService
{
    public function __construct(
        protected SyncOutboxRecorder $recorder,
        protected SyncTargetSchemaService $schemaService,
    ) {}

    public function isLocalRole(): bool
    {
        return config('sync.enabled') && config('sync.role') === 'local';
    }

    public function isCloudRole(): bool
    {
        return config('sync.enabled') && config('sync.role') === 'cloud';
    }

    public function pendingCount(): int
    {
        if (! Schema::hasTable('sync_queue')) {
            return 0;
        }

        return (int) SyncQueueItem::query()->whereNull('synced_at')->count();
    }

    public function remoteReachable(): bool
    {
        $url = config('sync.remote_url');
        $token = config('sync.token');

        if ($url === '' || $token === '') {
            return false;
        }

        $cacheSeconds = max(5, (int) config('sync.remote_ping_cache_seconds', 60));
        $cacheKey = 'sync:remote_reachable:'.md5($url);

        try {
            $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
            // Only trust positive cache — never stick on "No net" after one failed ping.
            if ($cached === true) {
                return true;
            }
        } catch (\Throwable) {
            // fall through to live ping
        }

        $ok = $this->pingRemote($url, $token);

        try {
            if ($ok) {
                \Illuminate\Support\Facades\Cache::put($cacheKey, true, $cacheSeconds);
            } else {
                \Illuminate\Support\Facades\Cache::put($cacheKey, false, 5);
            }
        } catch (\Throwable) {
            // ignore
        }

        return $ok;
    }

    private function pingRemote(string $url, string $token): bool
    {
        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->withToken($token)
                ->acceptJson()
                ->get($url.'/api/sync/ping');

            return $response->successful() && ($response->json('ok') === true);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Push pending local changes to hosting.
     *
     * @return array{ok: bool, pushed: int, pending: int, message: string}
     */
    public function push(bool $force = false): array
    {
        if (! $this->isLocalRole()) {
            return ['ok' => false, 'pushed' => 0, 'pending' => 0, 'message' => 'Sync only runs on local role.'];
        }

        $url = config('sync.remote_url');
        $token = config('sync.token');

        if ($url === '' || $token === '') {
            return ['ok' => false, 'pushed' => 0, 'pending' => $this->pendingCount(), 'message' => 'SYNC_REMOTE_URL / SYNC_TOKEN missing.'];
        }

        $batchSize = max(1, (int) config('sync.batch_size', 250));
        $pushTimeout = max(3, (int) config('sync.push_timeout_seconds', 20));
        $pushed = 0;

        if (! $this->shouldRunPushNow($force)) {
            return [
                'ok' => false,
                'pushed' => 0,
                'pending' => $this->pendingCount(),
                'message' => 'Push skipped (debounce). Click sync badge or run php artisan sync:cloud.',
            ];
        }

        $this->markPushAttempted();

        $this->ensureTargetSchemaOnce();

        while (true) {
            $items = SyncQueueItem::query()
                ->whereNull('synced_at')
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($items->isEmpty()) {
                break;
            }

            $payload = [
                'changes' => $items->map(fn (SyncQueueItem $item) => [
                    'id' => $item->id,
                    'table' => $item->table_name,
                    'key' => $item->record_key,
                    'action' => $item->action,
                    'payload' => $item->payload,
                ])->values()->all(),
            ];

            try {
                $response = Http::timeout($pushTimeout)
                    ->connectTimeout(3)
                    ->withToken($token)
                    ->acceptJson()
                    ->post($url.'/api/sync/push', $payload);
            } catch (Throwable $e) {
                foreach ($items as $item) {
                    $item->forceFill([
                        'attempts' => min(254, (int) $item->attempts + 1),
                        'last_error' => $e->getMessage(),
                    ])->save();
                }

                return [
                    'ok' => false,
                    'pushed' => $pushed,
                    'pending' => $this->pendingCount(),
                    'message' => 'Hosting unreachable: '.$e->getMessage(),
                ];
            }

            if (! $response->successful()) {
                $message = (string) ($response->json('message') ?? $response->body());
                foreach ($items as $item) {
                    $item->forceFill([
                        'attempts' => min(254, (int) $item->attempts + 1),
                        'last_error' => $message,
                    ])->save();
                }

                return [
                    'ok' => false,
                    'pushed' => $pushed,
                    'pending' => $this->pendingCount(),
                    'message' => 'Hosting rejected sync: '.$message,
                ];
            }

            $applied = collect($response->json('applied', []))->map(fn ($id) => (int) $id)->all();
            $failed = collect($response->json('failed', []))->keyBy(fn ($row) => (int) ($row['id'] ?? 0));
            $strippedForRetry = false;

            foreach ($items as $item) {
                if (in_array((int) $item->id, $applied, true)) {
                    $item->forceFill([
                        'synced_at' => now(),
                        'last_error' => null,
                    ])->save();
                    $pushed++;
                    continue;
                }

                $err = $failed->get((int) $item->id);
                $errorText = is_array($err) ? (string) ($err['error'] ?? 'unknown') : 'not applied';

                if (str_contains($errorText, 'Duplicate entry')) {
                    $item->forceFill([
                        'synced_at' => now(),
                        'last_error' => null,
                    ])->save();
                    $pushed++;

                    continue;
                }

                if ($this->stripUnknownColumnFromQueueItem($item, $errorText)) {
                    $strippedForRetry = true;

                    continue;
                }

                $item->forceFill([
                    'attempts' => min(254, (int) $item->attempts + 1),
                    'last_error' => $errorText,
                ])->save();
            }

            if ($failed->isNotEmpty() && ! $strippedForRetry) {
                break;
            }
        }

        $this->setMeta('last_push_at', now()->toIso8601String());
        $this->setMeta('last_push_ok', $pushed > 0 ? '1' : '0');

        $pending = $this->pendingCount();

        return [
            'ok' => $pending === 0 || $pushed > 0,
            'pushed' => $pushed,
            'pending' => $pending,
            'message' => $pushed > 0
                ? "Synced {$pushed} change(s) to hosting."
                : ($pending > 0 ? "{$pending} change(s) still pending on hosting." : 'Already up to date.'),
        ];
    }

    protected function stripUnknownColumnFromQueueItem(SyncQueueItem $item, string $errorText): bool
    {
        if (! preg_match("/Unknown column '([^']+)'/", $errorText, $matches)) {
            return false;
        }

        $column = $matches[1];

        // Never permanently drop kitchen/cashier printer columns — they must be
        // re-applied after hosting schema is repaired. Stripping them caused silent
        // "synced" rows without printer_ip on cloud.
        if (in_array($column, ['printer_ip', 'printer_port', 'printer_name'], true)
            || str_starts_with($column, 'cashier_printer_')) {
            $item->forceFill([
                'last_error' => "Hosting missing column `{$column}` — deploy Kitchen Agents schema, then sync:repair --queue-kitchen-agents --push",
            ])->save();

            return false;
        }

        $payload = $item->payload;
        if (! is_array($payload) || ! array_key_exists($column, $payload)) {
            return false;
        }

        unset($payload[$column]);
        $item->forceFill([
            'payload' => $payload,
            'last_error' => null,
        ])->save();

        return true;
    }

    /**
     * Apply a batch of changes on the cloud (hosting) database.
     *
     * @param  array<int, array{id?: int, table: string, key: string, action: string, payload?: ?array}>  $changes
     * @return array{applied: list<int>, failed: list<array{id: int, error: string}>}
     */
    public function applyIncoming(array $changes): array
    {
        $applied = [];
        $failed = [];

        if ($this->isCloudRole()) {
            $this->schemaService->ensureAll();
        }

        SyncOutboxRecorder::$applyingRemote = true;

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($changes as $change) {
                $clientId = (int) ($change['id'] ?? 0);
                $table = (string) ($change['table'] ?? '');
                $key = (string) ($change['key'] ?? '');
                $action = (string) ($change['action'] ?? '');
                $payload = $change['payload'] ?? null;

                try {
                    if ($table === '' || $key === '' || $this->recorder->isExcluded($table)) {
                        throw new \InvalidArgumentException('Invalid table/key.');
                    }

                    if (! Schema::hasTable($table) && Schema::connection('tenant')->hasTable($table)) {
                        $landlordDb = (string) config('database.connections.mysql.database', '');
                        if ($landlordDb !== '') {
                            config(['database.connections.tenant.database' => $landlordDb]);
                            DB::purge('tenant');
                        }
                    }

                    $connection = Schema::hasTable($table) ? DB::connection() : DB::connection('tenant');

                    if (! Schema::connection($connection->getName())->hasTable($table)) {
                        throw new \RuntimeException("Table missing: {$table}");
                    }

                    if ($action === 'delete') {
                        if ($table === 'inventory_units' && is_array($payload) && ! empty($payload['code'])) {
                            $connection->table($table)->where('code', InventoryUnit::normalizeCode((string) $payload['code']))->delete();
                        } else {
                            $connection->table($table)->where('id', $key)->delete();
                        }
                    } elseif ($action === 'upsert') {
                        if (! is_array($payload) || $payload === []) {
                            throw new \InvalidArgumentException('Empty payload.');
                        }

                        $payload = SyncMediaFiles::restoreFromPayload($payload);

                        if ($table === 'inventory_units') {
                            $this->upsertInventoryUnitByCode($connection, $payload);
                        } elseif ($table === 'inventory_unit_conversions') {
                            $this->upsertInventoryUnitConversionByCode($connection, $payload);
                        } elseif ($table === 'employee_staff_categories') {
                            $this->upsertEmployeeStaffCategoryBySlug($connection, $payload);
                        } elseif ($table === 'employees') {
                            unset($payload['id']);
                            $payload = $this->resolveEmployeeStaffCategoryId($connection, $payload);
                            $payload = $this->filterPayloadForTable($connection, $table, $payload);
                            unset($payload['staff_category_slug']);
                            $connection->table($table)->updateOrInsert(['id' => $key], $payload);
                        } else {
                            unset($payload['id']);
                            // Kitchen printer columns must exist before filterPayloadForTable strips them.
                            if ($table === 'inventory_departments') {
                                $this->schemaService->ensureAll();
                            }
                            $payload = $this->filterPayloadForTable($connection, $table, $payload);
                            $connection->table($table)->updateOrInsert(['id' => $key], $payload);

                            if ($table === 'settings') {
                                \App\Models\Setting::forgetCachesAfterSync(
                                    isset($payload['company_id']) ? (int) $payload['company_id'] : null,
                                    isset($payload['key']) ? (string) $payload['key'] : null
                                );
                            }
                        }
                    } else {
                        throw new \InvalidArgumentException("Unknown action: {$action}");
                    }

                    $applied[] = $clientId;
                } catch (Throwable $e) {
                    $message = $e->getMessage();
                    if ($action === 'upsert' && str_contains($message, 'Duplicate entry')) {
                        $applied[] = $clientId;

                        continue;
                    }

                    Log::warning('sync.apply_failed', [
                        'table' => $table,
                        'key' => $key,
                        'error' => $message,
                    ]);
                    $failed[] = ['id' => $clientId, 'error' => $message];
                }
            }
        } finally {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } catch (Throwable) {
                // ignore
            }
            SyncOutboxRecorder::$applyingRemote = false;
        }

        $this->setMeta('last_receive_at', now()->toIso8601String());

        return compact('applied', 'failed');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function filterPayloadForTable(\Illuminate\Database\Connection $connection, string $table, array $payload): array
    {
        $columns = Schema::connection($connection->getName())->getColumnListing($table);
        if ($columns === []) {
            return $payload;
        }

        return array_intersect_key($payload, array_flip($columns));
    }

    /**
     * Hosting: export rows for cafe PC pull (online → offline).
     *
     * @param  list<string>|null  $tables
     * @return array{ok: bool, changes: list<array<string, mixed>>, next_since: ?string, has_more: bool, message: string}
     */
    public function exportPullBatch(?string $since, ?array $tables, int $limit = 200): array
    {
        if (! $this->isCloudRole()) {
            return [
                'ok' => false,
                'changes' => [],
                'next_since' => $since,
                'has_more' => false,
                'message' => 'Pull export only runs on cloud role.',
            ];
        }

        $tables = $tables ?: $this->resolvePullTables();
        $tables = array_values(array_filter($tables, fn ($t) => is_string($t) && $t !== '' && ! $this->recorder->isExcluded($t)));
        $limit = max(1, min(500, $limit));
        $lookbackDays = max(7, (int) config('sync.pull_lookback_days', 120));

        $sinceRaw = is_string($since) ? trim($since) : '';
        $sinceAt = null;
        if ($sinceRaw !== '') {
            try {
                $sinceAt = \Carbon\Carbon::parse($sinceRaw);
            } catch (Throwable) {
                $sinceAt = null;
            }
        }
        $hasSinceCursor = $sinceAt !== null;
        if ($sinceAt === null) {
            $sinceAt = now()->subDays($lookbackDays);
        }

        $changes = [];
        $nextSince = $sinceAt->copy();
        $hasMore = false;

        $order = [
            'companies', 'users', 'settings', 'contacts',
            'inventory_units', 'inventory_unit_conversions', 'inventory_categories', 'inventory_departments', 'inventory_products',
            'pos_sitting_areas', 'pos_tables', 'pos_sessions', 'pos_orders', 'pos_order_items', 'pos_payments', 'credit_ledger',
        ];
        usort($tables, function ($a, $b) use ($order) {
            $ia = array_search($a, $order, true);
            $ib = array_search($b, $order, true);
            $ia = $ia === false ? 100 : $ia;
            $ib = $ib === false ? 100 : $ib;

            return $ia <=> $ib;
        });

        $remaining = $limit;
        foreach ($tables as $table) {
            if ($remaining <= 0) {
                $hasMore = true;
                break;
            }

            $connection = $this->connectionForTable($table);
            if ($connection === null) {
                continue;
            }

            $schema = Schema::connection($connection->getName());
            if (! $schema->hasTable($table) || ! $schema->hasColumn($table, 'id')) {
                continue;
            }

            $q = $connection->table($table);
            if ($schema->hasColumn($table, 'updated_at')) {
                $q->where('updated_at', '>', $sinceAt->toDateTimeString())
                    ->orderBy('updated_at')
                    ->orderBy('id');
            } elseif ($schema->hasColumn($table, 'created_at')) {
                $q->where('created_at', '>', $sinceAt->toDateTimeString())
                    ->orderBy('created_at')
                    ->orderBy('id');
            } else {
                // No timestamps: only full export when cursor empty (avoid re-sending whole table every minute)
                if ($hasSinceCursor) {
                    continue;
                }
                $q->orderBy('id');
            }

            $rows = $q->limit($remaining + 1)->get();
            if ($rows->count() > $remaining) {
                $hasMore = true;
                $rows = $rows->take($remaining);
            }

            foreach ($rows as $row) {
                $attrs = (array) $row;
                $id = $attrs['id'] ?? null;
                if ($id === null) {
                    continue;
                }
                $changes[] = [
                    'id' => 0,
                    'table' => $table,
                    'key' => (string) $id,
                    'action' => 'upsert',
                    'payload' => $attrs,
                ];
                $stamp = $attrs['updated_at'] ?? $attrs['created_at'] ?? null;
                if ($stamp) {
                    try {
                        $ts = \Carbon\Carbon::parse($stamp);
                        if ($ts->gt($nextSince)) {
                            $nextSince = $ts;
                        }
                    } catch (Throwable) {
                        // ignore
                    }
                }
                $remaining--;
            }
        }

        return [
            'ok' => true,
            'changes' => $changes,
            'next_since' => $nextSince->toIso8601String(),
            'has_more' => $hasMore,
            'message' => count($changes) > 0
                ? 'Exported '.count($changes).' row(s).'
                : 'No newer rows.',
        ];
    }

    /**
     * Hosting: export many tables in one response using per-table cursors (fast pull).
     *
     * @param  array<string, ?string>  $cursors  table => since iso/null
     * @return array{ok: bool, changes: list<array<string, mixed>>, cursors: array<string, string>, has_more: bool, message: string}
     */
    public function exportPullMulti(array $cursors, int $limit = 400): array
    {
        if (! $this->isCloudRole()) {
            return [
                'ok' => false,
                'changes' => [],
                'cursors' => [],
                'has_more' => false,
                'message' => 'Pull export only runs on cloud role.',
            ];
        }

        $limit = max(1, min(500, $limit));
        $lookbackDays = max(7, (int) config('sync.pull_lookback_days', 120));
        $lookback = now()->subDays($lookbackDays);
        $changes = [];
        $nextCursors = [];
        $hasMore = false;
        $remaining = $limit;

        $order = [
            'companies', 'users', 'settings', 'contacts',
            'inventory_products', 'inventory_product_stocks',
            'pos_orders', 'pos_order_items', 'pos_payments', 'credit_ledger',
        ];
        $tables = array_keys($cursors);
        usort($tables, function ($a, $b) use ($order) {
            $ia = array_search($a, $order, true);
            $ib = array_search($b, $order, true);

            return ($ia === false ? 100 : $ia) <=> ($ib === false ? 100 : $ib);
        });

        foreach ($tables as $table) {
            if (! is_string($table) || $table === '' || $this->recorder->isExcluded($table)) {
                continue;
            }

            $sinceRaw = $cursors[$table] ?? null;
            $sinceAt = null;
            if (is_string($sinceRaw) && trim($sinceRaw) !== '') {
                try {
                    $sinceAt = \Carbon\Carbon::parse($sinceRaw);
                } catch (Throwable) {
                    $sinceAt = null;
                }
            }
            $hasSinceCursor = $sinceAt !== null;
            if ($sinceAt === null) {
                $sinceAt = $lookback->copy();
            }

            $tableNext = $sinceAt->copy();

            if ($remaining <= 0) {
                $hasMore = true;
                $nextCursors[$table] = $sinceAt->toIso8601String();

                continue;
            }

            $connection = $this->connectionForTable($table);
            if ($connection === null) {
                $nextCursors[$table] = $sinceAt->toIso8601String();

                continue;
            }

            $schema = Schema::connection($connection->getName());
            if (! $schema->hasTable($table) || ! $schema->hasColumn($table, 'id')) {
                $nextCursors[$table] = $sinceAt->toIso8601String();

                continue;
            }

            $q = $connection->table($table);
            if ($schema->hasColumn($table, 'updated_at')) {
                $q->where('updated_at', '>', $sinceAt->toDateTimeString())
                    ->orderBy('updated_at')
                    ->orderBy('id');
            } elseif ($schema->hasColumn($table, 'created_at')) {
                $q->where('created_at', '>', $sinceAt->toDateTimeString())
                    ->orderBy('created_at')
                    ->orderBy('id');
            } else {
                if ($hasSinceCursor) {
                    $nextCursors[$table] = $sinceAt->toIso8601String();

                    continue;
                }
                $q->orderBy('id');
            }

            $rows = $q->limit($remaining + 1)->get();
            if ($rows->count() > $remaining) {
                $hasMore = true;
                $rows = $rows->take($remaining);
            }

            foreach ($rows as $row) {
                $attrs = (array) $row;
                $id = $attrs['id'] ?? null;
                if ($id === null) {
                    continue;
                }
                $changes[] = [
                    'id' => 0,
                    'table' => $table,
                    'key' => (string) $id,
                    'action' => 'upsert',
                    'payload' => $attrs,
                ];
                $stamp = $attrs['updated_at'] ?? $attrs['created_at'] ?? null;
                if ($stamp) {
                    try {
                        $ts = \Carbon\Carbon::parse($stamp);
                        if ($ts->gt($tableNext)) {
                            $tableNext = $ts;
                        }
                    } catch (Throwable) {
                        // ignore
                    }
                }
                $remaining--;
            }

            $nextCursors[$table] = $tableNext->toIso8601String();
        }

        return [
            'ok' => true,
            'changes' => $changes,
            'cursors' => $nextCursors,
            'has_more' => $hasMore,
            'message' => count($changes) > 0
                ? 'Exported '.count($changes).' row(s).'
                : 'No newer rows.',
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array{ok: bool, changes: list<array<string, mixed>>, message: string}
     */
    public function exportRowsByIds(string $table, array $ids, string $by = 'id'): array
    {
        if (! $this->isCloudRole()) {
            return ['ok' => false, 'changes' => [], 'message' => 'Pull export only runs on cloud role.'];
        }

        $table = trim($table);
        if ($table === '' || $this->recorder->isExcluded($table)) {
            return ['ok' => false, 'changes' => [], 'message' => 'Invalid table.'];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));
        if ($ids === []) {
            return ['ok' => true, 'changes' => [], 'message' => 'No ids.'];
        }

        $connection = $this->connectionForTable($table);
        if ($connection === null) {
            return ['ok' => false, 'changes' => [], 'message' => "Table missing: {$table}"];
        }

        $schema = Schema::connection($connection->getName());
        $column = $by === 'order_id' && $schema->hasColumn($table, 'order_id') ? 'order_id' : 'id';

        $rows = $connection->table($table)->whereIn($column, $ids)->get();
        $changes = [];
        foreach ($rows as $row) {
            $attrs = (array) $row;
            $id = $attrs['id'] ?? null;
            if ($id === null) {
                continue;
            }
            $changes[] = [
                'id' => 0,
                'table' => $table,
                'key' => (string) $id,
                'action' => 'upsert',
                'payload' => $attrs,
            ];
        }

        return [
            'ok' => true,
            'changes' => $changes,
            'message' => 'Exported '.count($changes).' row(s).',
        ];
    }

    /**
     * Push local outbox then optionally pull hosting changes.
     *
     * @return array{ok: bool, pushed: int, pending: int, pulled: int, failed: int, message: string, online: bool}
     */
    public function syncBoth(bool $force = false, bool $resetPullCursors = false, bool $withPull = true): array
    {
        $push = $this->push($force);
        $pull = [
            'ok' => true,
            'pulled' => 0,
            'failed' => 0,
            'message' => 'Pull skipped.',
            'online' => $this->remoteReachable(),
        ];

        // Caller sets $withPull; master gate is SYNC_PULL_ENABLED (online → offline off by default).
        if ($withPull && config('sync.pull_enabled', false)) {
            $pull = $this->pull($force, $resetPullCursors);
        }

        $online = (bool) ($pull['online'] ?? false) || (bool) ($push['ok'] ?? false);
        if (! $online) {
            $online = $this->remoteReachable();
        }
        // Push debounce skip is not a connectivity failure.
        $ok = ((($push['ok'] ?? false) || (($push['pending'] ?? 0) === 0)) && ($pull['ok'] ?? false))
            || $online;

        return [
            'ok' => $ok,
            'pushed' => (int) ($push['pushed'] ?? 0),
            'pending' => (int) ($push['pending'] ?? $this->pendingCount()),
            'pulled' => (int) ($pull['pulled'] ?? 0),
            'failed' => (int) ($pull['failed'] ?? 0),
            'message' => trim(($push['message'] ?? '').' '.($pull['message'] ?? '')),
            'online' => $online,
            'push' => $push,
            'pull' => $pull,
        ];
    }

    /**
     * Tables to pull from hosting. "*" / "all" / empty = every syncable table.
     *
     * @return list<string>
     */
    public function resolvePullTables(): array
    {
        $configured = config('sync.pull_tables', ['*']);
        if (! is_array($configured) || $configured === []) {
            return $this->syncableTables();
        }

        $normalized = array_values(array_filter($configured, fn ($t) => is_string($t) && $t !== ''));
        if ($normalized === [] || in_array('*', $normalized, true) || in_array('all', $normalized, true)) {
            return $this->syncableTables();
        }

        return array_values(array_filter($normalized, fn ($t) => ! $this->recorder->isExcluded($t)));
    }

    /**
     * Cafe PC: pull hosting DB changes into local (full DB when pull_tables=*).
     *
     * @return array{ok: bool, pulled: int, failed: int, message: string, online: bool}
     */
    public function pull(bool $force = false, bool $resetCursors = false): array
    {
        if (! $this->isLocalRole()) {
            return ['ok' => false, 'pulled' => 0, 'failed' => 0, 'message' => 'Pull only runs on local role.', 'online' => false];
        }

        if (! config('sync.pull_enabled', false)) {
            return [
                'ok' => true,
                'pulled' => 0,
                'failed' => 0,
                'message' => 'Online → offline pull band hai (one-way sync: cafe → hosting only).',
                'online' => $this->remoteReachable(),
            ];
        }

        $url = config('sync.remote_url');
        $token = config('sync.token');
        if ($url === '' || $token === '') {
            return ['ok' => false, 'pulled' => 0, 'failed' => 0, 'message' => 'SYNC_REMOTE_URL / SYNC_TOKEN missing.', 'online' => false];
        }

        if (! $force && ! $this->shouldRunPullNow()) {
            return [
                'ok' => true,
                'pulled' => 0,
                'failed' => 0,
                'message' => 'Pull skipped (debounce).',
                'online' => $this->remoteReachable(),
            ];
        }

        $this->markPullAttempted();

        if ($resetCursors) {
            $this->resetPullCursors();
        }

        // Reset cursors = full table list; normal/background = hot + rotated cold (fast)
        $tables = $this->tablesForPullCycle($resetCursors);
        $pulled = 0;
        $failed = 0;
        $posOrderIds = [];
        $pullTimeout = max(10, (int) config('sync.push_timeout_seconds', 20));
        $limit = max(150, min(500, (int) config('sync.batch_size', 250)));

        try {
            $multi = $this->pullViaMulti($tables, $url, $token, $pullTimeout, $limit, $posOrderIds);
            if ($multi !== null) {
                $pulled += $multi['pulled'];
                $failed += $multi['failed'];
                    if ($multi['fatal'] !== null) {
                        return [
                            'ok' => false,
                            'pulled' => $pulled,
                            'failed' => $failed,
                            'message' => $multi['fatal'],
                            // Hosting answered (even with error) — do not show "No net"
                            'online' => $multi['online'] || $this->remoteReachable(),
                        ];
                    }
            } else {
                // Hosting without pull-multi: fall back to a few parallel single-table calls
                $fallback = $this->pullViaParallel($tables, $url, $token, $pullTimeout, $limit, $posOrderIds);
                $pulled += $fallback['pulled'];
                $failed += $fallback['failed'];
                if ($fallback['fatal'] !== null) {
                    return [
                        'ok' => false,
                        'pulled' => $pulled,
                        'failed' => $failed,
                        'message' => $fallback['fatal'],
                        'online' => $fallback['online'] || $this->remoteReachable(),
                    ];
                }
            }

            if ($posOrderIds !== []) {
                $related = $this->pullRelatedPosSales(array_values($posOrderIds), $url, $token);
                $pulled += $related['pulled'];
                $failed += $related['failed'];
            }
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'pulled' => $pulled,
                'failed' => $failed,
                'message' => 'Pull error: '.$e->getMessage(),
                'online' => $this->remoteReachable(),
            ];
        }

        $this->setMeta('last_pull_ok', $failed === 0 ? '1' : '0');

        return [
            'ok' => $failed === 0,
            'pulled' => $pulled,
            'failed' => $failed,
            'message' => $pulled > 0
                ? "Pulled {$pulled} online row(s) to local.".($failed > 0 ? " {$failed} failed." : '')
                : ($failed > 0 ? "{$failed} pull row(s) failed." : 'Local DB already matches hosting (incremental).'),
            'online' => true,
        ];
    }

    /**
     * One HTTP round-trip for many tables (requires hosting /api/sync/pull-multi).
     *
     * @param  list<string>  $tables
     * @param  array<int, int>  $posOrderIds
     * @return array{pulled: int, failed: int, fatal: ?string, online: bool}|null  null = endpoint missing
     */
    protected function pullViaMulti(array $tables, string $url, string $token, int $pullTimeout, int $limit, array &$posOrderIds): ?array
    {
        $pulled = 0;
        $failed = 0;
        $loops = 0;

        do {
            $loops++;
            $cursors = [];
            foreach ($tables as $table) {
                $cursors[$table] = $this->getMeta('last_pull_at:'.$table);
            }

            try {
                $response = Http::timeout($pullTimeout)
                    ->connectTimeout(2)
                    ->withToken($token)
                    ->acceptJson()
                    ->post($url.'/api/sync/pull-multi', [
                        'cursors' => $cursors,
                        'limit' => $limit,
                    ]);
            } catch (Throwable) {
                return null;
            }

            if ($response->status() === 404 || $response->status() === 405) {
                return null;
            }

            if ($response->status() === 429) {
                usleep(500000);
                $response = Http::timeout($pullTimeout)
                    ->connectTimeout(2)
                    ->withToken($token)
                    ->acceptJson()
                    ->post($url.'/api/sync/pull-multi', [
                        'cursors' => $cursors,
                        'limit' => $limit,
                    ]);
            }

            if (! $response->successful()) {
                // Got an HTTP response from hosting — network is up
                return [
                    'pulled' => $pulled,
                    'failed' => $failed,
                    'fatal' => 'Hosting pull-multi failed: HTTP '.$response->status(),
                    'online' => true,
                ];
            }

            $body = $response->json();
            if (! is_array($body) || ($body['ok'] ?? false) !== true) {
                return [
                    'pulled' => $pulled,
                    'failed' => $failed,
                    'fatal' => (string) ($body['message'] ?? 'Hosting pull-multi rejected.'),
                    'online' => true,
                ];
            }

            $changes = is_array($body['changes'] ?? null) ? $body['changes'] : [];
            $changes = $this->filterPullChangesSkippingLocalPending($changes);
            if ($changes !== []) {
                $result = $this->applyIncoming($changes);
                $pulled += count($result['applied']);
                $failed += count($result['failed']);
                foreach ($changes as $change) {
                    if (($change['table'] ?? '') === 'credit_ledger' && is_array($change['payload'] ?? null)) {
                        $oid = (int) ($change['payload']['pos_order_id'] ?? 0);
                        if ($oid > 0) {
                            $posOrderIds[$oid] = $oid;
                        }
                    }
                }
            }

            $nextCursors = is_array($body['cursors'] ?? null) ? $body['cursors'] : [];
            foreach ($nextCursors as $table => $since) {
                if (! is_string($table) || ! is_string($since) || $since === '') {
                    continue;
                }
                $this->setMeta('last_pull_at:'.$table, $since);
                $this->setMeta('last_pull_at', $since);
            }

            $hasMore = (bool) ($body['has_more'] ?? false);
        } while ($hasMore && $loops < 20);

        return ['pulled' => $pulled, 'failed' => $failed, 'fatal' => null, 'online' => true];
    }

    /**
     * @param  list<string>  $tables
     * @param  array<int, int>  $posOrderIds
     * @return array{pulled: int, failed: int, fatal: ?string, online: bool}
     */
    protected function pullViaParallel(array $tables, string $url, string $token, int $pullTimeout, int $limit, array &$posOrderIds): array
    {
        $pulled = 0;
        $failed = 0;
        $parallel = max(1, min(4, (int) config('sync.pull_parallel', 3)));
        $chunkIndex = 0;

        foreach (array_chunk($tables, $parallel) as $chunk) {
            if ($chunkIndex > 0) {
                usleep(80000);
            }
            $chunkIndex++;

            $responses = Http::pool(function ($pool) use ($chunk, $url, $token, $pullTimeout, $limit) {
                foreach ($chunk as $table) {
                    $since = $this->getMeta('last_pull_at:'.$table);
                    $pool->as($table)
                        ->timeout($pullTimeout)
                        ->connectTimeout(2)
                        ->withToken($token)
                        ->acceptJson()
                        ->get($url.'/api/sync/pull', [
                            'since' => $since,
                            'tables' => [$table],
                            'limit' => $limit,
                        ]);
                }
            });

            foreach ($chunk as $table) {
                $response = $responses[$table] ?? null;
                if ($response instanceof \Illuminate\Http\Client\Response && $response->status() === 429) {
                    usleep(400000);
                    $response = Http::timeout($pullTimeout)
                        ->connectTimeout(2)
                        ->withToken($token)
                        ->acceptJson()
                        ->get($url.'/api/sync/pull', [
                            'since' => $this->getMeta('last_pull_at:'.$table),
                            'tables' => [$table],
                            'limit' => $limit,
                        ]);
                }
                if (! $response instanceof \Illuminate\Http\Client\Response) {
                    $failed++;

                    continue;
                }

                $apply = $this->consumePullResponse($response, $table, $url, $token, $pullTimeout, $limit, $posOrderIds);
                if ($apply['fatal'] !== null) {
                    return [
                        'pulled' => $pulled + $apply['pulled'],
                        'failed' => $failed + $apply['failed'],
                        'fatal' => $apply['fatal'],
                        'online' => $apply['online'],
                    ];
                }
                $pulled += $apply['pulled'];
                $failed += $apply['failed'];
            }
        }

        return ['pulled' => $pulled, 'failed' => $failed, 'fatal' => null, 'online' => true];
    }

    /**
     * Background: hot tables every cycle + rotate cold tables.
     * Force: every syncable table.
     *
     * @return list<string>
     */
    public function tablesForPullCycle(bool $full = false): array
    {
        $all = $this->resolvePullTables();
        if ($full || $all === []) {
            return $all;
        }

        $hotCfg = config('sync.pull_hot_tables', []);
        $hot = [];
        if (is_array($hotCfg)) {
            foreach ($hotCfg as $t) {
                if (is_string($t) && in_array($t, $all, true)) {
                    $hot[] = $t;
                }
            }
        }

        $cold = array_values(array_diff($all, $hot));
        $perCycle = max(3, (int) config('sync.pull_cold_per_cycle', 5));
        if ($cold === []) {
            return $hot !== [] ? $hot : $all;
        }

        $offset = (int) ($this->getMeta('pull_rotate_offset') ?: '0');
        $n = count($cold);
        $slice = [];
        for ($i = 0; $i < min($perCycle, $n); $i++) {
            $slice[] = $cold[($offset + $i) % $n];
        }
        $this->setMeta('pull_rotate_offset', (string) (($offset + $perCycle) % $n));

        return array_values(array_unique(array_merge($hot, $slice)));
    }

    /**
     * @param  array<int, int>  $posOrderIds
     * @return array{pulled: int, failed: int, fatal: ?string, online: bool}
     */
    protected function consumePullResponse(
        \Illuminate\Http\Client\Response $response,
        string $table,
        string $url,
        string $token,
        int $pullTimeout,
        int $limit,
        array &$posOrderIds
    ): array {
        $pulled = 0;
        $failed = 0;

        if (! $response->successful()) {
            return [
                'pulled' => 0,
                'failed' => 0,
                'fatal' => 'Hosting pull failed: HTTP '.$response->status().' — hosting par latest code deploy karein (/api/sync/pull).',
                'online' => true,
            ];
        }

        $since = $this->getMeta('last_pull_at:'.$table);
        $loops = 0;
        $body = $response->json();

        while (true) {
            $loops++;
            if (! is_array($body) || ($body['ok'] ?? false) !== true) {
                return [
                    'pulled' => $pulled,
                    'failed' => $failed,
                    'fatal' => (string) ($body['message'] ?? 'Hosting pull rejected.'),
                    'online' => true,
                ];
            }

            $changes = is_array($body['changes'] ?? null) ? $body['changes'] : [];
            $changes = $this->filterPullChangesSkippingLocalPending($changes);

            if ($changes !== []) {
                $result = $this->applyIncoming($changes);
                $pulled += count($result['applied']);
                $failed += count($result['failed']);

                foreach ($changes as $change) {
                    if (($change['table'] ?? '') === 'credit_ledger' && is_array($change['payload'] ?? null)) {
                        $oid = (int) ($change['payload']['pos_order_id'] ?? 0);
                        if ($oid > 0) {
                            $posOrderIds[$oid] = $oid;
                        }
                    }
                }
            }

            $next = $body['next_since'] ?? null;
            if (is_string($next) && $next !== '') {
                $since = $next;
                $this->setMeta('last_pull_at:'.$table, $next);
                $this->setMeta('last_pull_at', $next);
            }

            $hasMore = (bool) ($body['has_more'] ?? false);
            if (! $hasMore || $loops >= 40) {
                break;
            }

            $follow = Http::timeout($pullTimeout)
                ->connectTimeout(2)
                ->withToken($token)
                ->acceptJson()
                ->get($url.'/api/sync/pull', [
                    'since' => $since,
                    'tables' => [$table],
                    'limit' => $limit,
                ]);

            if (! $follow->successful()) {
                return [
                    'pulled' => $pulled,
                    'failed' => $failed,
                    'fatal' => 'Hosting pull failed: HTTP '.$follow->status(),
                    'online' => true,
                ];
            }
            $body = $follow->json();
        }

        return ['pulled' => $pulled, 'failed' => $failed, 'fatal' => null, 'online' => true];
    }

    /** Clear pull cursors so next pull uses lookback window again. */
    public function resetPullCursors(): void
    {
        if (! Schema::hasTable('sync_meta')) {
            return;
        }

        DB::table('sync_meta')
            ->where(function ($q) {
                $q->where('meta_key', 'last_pull_at')
                    ->orWhere('meta_key', 'like', 'last_pull_at:%')
                    ->orWhere('meta_key', 'pull_rotate_offset');
            })
            ->delete();
    }

    /**
     * @param  list<int>  $orderIds
     * @return array{pulled: int, failed: int}
     */
    protected function pullRelatedPosSales(array $orderIds, string $url, string $token): array
    {
        $pulled = 0;
        $failed = 0;
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if ($orderIds === []) {
            return compact('pulled', 'failed');
        }

        foreach ([
            ['table' => 'pos_orders', 'by' => 'id'],
            ['table' => 'pos_order_items', 'by' => 'order_id'],
            ['table' => 'pos_payments', 'by' => 'order_id'],
        ] as $spec) {
            try {
                $response = Http::timeout(max(8, (int) config('sync.push_timeout_seconds', 12)))
                    ->connectTimeout(3)
                    ->withToken($token)
                    ->acceptJson()
                    ->post($url.'/api/sync/pull-ids', [
                        'table' => $spec['table'],
                        'ids' => $orderIds,
                        'by' => $spec['by'],
                    ]);
                if (! $response->successful()) {
                    continue;
                }
                $body = $response->json();
                $changes = is_array($body['changes'] ?? null) ? $body['changes'] : [];
                $changes = $this->filterPullChangesSkippingLocalPending($changes);
                if ($changes === []) {
                    continue;
                }
                $result = $this->applyIncoming($changes);
                $pulled += count($result['applied']);
                $failed += count($result['failed']);
            } catch (Throwable) {
                // ignore one child table failure
            }
        }

        return compact('pulled', 'failed');
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     * @return list<array<string, mixed>>
     */
    protected function filterPullChangesSkippingLocalPending(array $changes): array
    {
        if (! Schema::hasTable('sync_queue')) {
            return $changes;
        }

        $out = [];
        foreach ($changes as $change) {
            $table = (string) ($change['table'] ?? '');
            $key = (string) ($change['key'] ?? '');
            if ($table === '' || $key === '') {
                continue;
            }
            $pending = SyncQueueItem::query()
                ->whereNull('synced_at')
                ->where('table_name', $table)
                ->where('record_key', $key)
                ->exists();
            if ($pending) {
                continue;
            }
            $out[] = $change;
        }

        return $out;
    }

    protected function connectionForTable(string $table): ?\Illuminate\Database\Connection
    {
        if (Schema::hasTable($table)) {
            return DB::connection();
        }
        if (Schema::connection('tenant')->hasTable($table)) {
            return DB::connection('tenant');
        }

        return null;
    }

    private function shouldRunPullNow(): bool
    {
        $debounce = max(5, (int) config('sync.pull_debounce_seconds', config('sync.push_debounce_seconds', 15)));
        try {
            $last = \Illuminate\Support\Facades\Cache::get('sync:last_pull_attempt');

            return ! is_numeric($last) || (time() - (int) $last) >= $debounce;
        } catch (\Throwable) {
            return true;
        }
    }

    private function markPullAttempted(): void
    {
        try {
            \Illuminate\Support\Facades\Cache::put('sync:last_pull_attempt', time(), now()->addHours(2));
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * Queue every existing row once (first-time full mirror to hosting).
     */
    public function queueFullSnapshot(): int
    {
        if (! $this->isLocalRole() || ! Schema::hasTable('sync_queue')) {
            return 0;
        }

        $queued = 0;
        $tables = $this->syncableTables();

        foreach ($tables as $table) {
            if (! Schema::hasColumn($table, 'id')) {
                continue;
            }

            DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, &$queued) {
                foreach ($rows as $row) {
                    $attrs = (array) $row;
                    $key = (string) ($attrs['id'] ?? '');
                    if ($key === '') {
                        continue;
                    }

                    $attrs = $this->recorder->enrichPayload($table, $attrs);

                    $pending = SyncQueueItem::query()
                        ->whereNull('synced_at')
                        ->where('table_name', $table)
                        ->where('record_key', $key)
                        ->first();

                    if ($pending) {
                        $pending->fill([
                            'action' => 'upsert',
                            'payload' => $attrs,
                        ])->save();
                    } else {
                        SyncQueueItem::query()->create([
                            'table_name' => $table,
                            'record_key' => $key,
                            'action' => 'upsert',
                            'payload' => $attrs,
                        ]);
                    }
                    $queued++;
                }
            });
        }

        return $queued;
    }

    /**
     * @return list<string>
     */
    public function syncableTables(): array
    {
        $db = DB::getDatabaseName();
        $tables = collect(DB::select('SHOW TABLES'))
            ->map(function ($row) {
                $arr = (array) $row;

                return (string) reset($arr);
            })
            ->reject(fn (string $t) => $this->recorder->isExcluded($t))
            ->values()
            ->all();

        return $tables;
    }

    public function status(): array
    {
        $online = $this->isLocalRole() ? $this->remoteReachable() : true;

        return [
            'enabled' => (bool) config('sync.enabled'),
            'role' => (string) config('sync.role'),
            'online' => $online,
            'pending' => $this->pendingCount(),
            'remote_url' => (string) config('sync.remote_url'),
            'last_push_at' => $this->getMeta('last_push_at'),
            'last_receive_at' => $this->getMeta('last_receive_at'),
            'last_pull_at' => $this->getMeta('last_pull_at'),
            'auto_push' => (bool) config('sync.auto_push_heartbeat', true),
            'auto_pull' => (bool) config('sync.pull_enabled', false) && (bool) config('sync.auto_pull', false),
            'pull_enabled' => (bool) config('sync.pull_enabled', false),
            'cloud_read_only' => config('sync.role') === 'cloud' && (bool) config('sync.cloud_read_only', true),
            'pull_tables' => count($this->resolvePullTables()),
        ];
    }

    private function shouldRunPushNow(bool $force = false): bool
    {
        if ($force) {
            return true;
        }

        $debounce = max(5, (int) config('sync.push_debounce_seconds', 15));

        try {
            $last = \Illuminate\Support\Facades\Cache::get('sync:last_push_attempt');

            return ! is_numeric($last) || (time() - (int) $last) >= $debounce;
        } catch (\Throwable) {
            return true;
        }
    }

    private function markPushAttempted(): void
    {
        try {
            \Illuminate\Support\Facades\Cache::put('sync:last_push_attempt', time(), now()->addHours(2));
        } catch (\Throwable) {
            // ignore
        }
    }

    private function ensureTargetSchemaOnce(): void
    {
        try {
            if (\Illuminate\Support\Facades\Cache::get('sync:schema_ensured')) {
                return;
            }
        } catch (\Throwable) {
            // run ensure below
        }

        $this->schemaService->ensureAll();

        try {
            \Illuminate\Support\Facades\Cache::put('sync:schema_ensured', true, now()->addHours(6));
        } catch (\Throwable) {
            // ignore
        }
    }

    protected function setMeta(string $key, string $value): void
    {
        if (! Schema::hasTable('sync_meta')) {
            return;
        }

        DB::table('sync_meta')->updateOrInsert(
            ['meta_key' => $key],
            ['meta_value' => $value, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    protected function getMeta(string $key): ?string
    {
        if (! Schema::hasTable('sync_meta')) {
            return null;
        }

        $row = DB::table('sync_meta')->where('meta_key', $key)->first();

        return $row?->meta_value;
    }

    /**
     * Staff categories sync by slug — local/cloud row IDs may differ.
     *
     * @param  \Illuminate\Database\Connection  $connection
     * @param  array<string, mixed>  $payload
     */
    protected function upsertEmployeeStaffCategoryBySlug($connection, array $payload): void
    {
        $slug = (string) ($payload['slug'] ?? '');
        if ($slug === '') {
            throw new \InvalidArgumentException('Staff category slug missing.');
        }

        $companyId = $payload['company_id'] ?? null;
        unset($payload['id']);

        $match = ['slug' => $slug];
        if ($companyId !== null) {
            $match['company_id'] = $companyId;
        }

        $connection->table('employee_staff_categories')->updateOrInsert($match, $payload);
    }

    /**
     * @param  \Illuminate\Database\Connection  $connection
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function resolveEmployeeStaffCategoryId($connection, array $payload): array
    {
        $slug = (string) ($payload['staff_category_slug'] ?? '');
        if ($slug === '') {
            return $payload;
        }

        $query = $connection->table('employee_staff_categories')->where('slug', $slug);
        if (isset($payload['company_id'])) {
            $query->where('company_id', $payload['company_id']);
        }

        $hostingId = (int) $query->value('id');
        if ($hostingId > 0) {
            $payload['staff_category_id'] = $hostingId;
        } else {
            $payload['staff_category_id'] = null;
        }

        return $payload;
    }

    /**
     * Units sync by code — local/cloud row IDs often differ (seeder order).
     *
     * @param  \Illuminate\Database\Connection  $connection
     * @param  array<string, mixed>  $payload
     */
    protected function upsertInventoryUnitByCode($connection, array $payload): void
    {
        $code = InventoryUnit::normalizeCode((string) ($payload['code'] ?? ''));
        if ($code === '') {
            throw new \InvalidArgumentException('Unit code missing.');
        }

        unset($payload['id']);
        $payload['code'] = $code;

        $connection->table('inventory_units')->updateOrInsert(
            ['code' => $code],
            $payload
        );
    }

    /**
     * Conversion rules sync by unit codes — avoids wrong from/to when IDs differ on hosting.
     *
     * @param  \Illuminate\Database\Connection  $connection
     * @param  array<string, mixed>  $payload
     */
    protected function upsertInventoryUnitConversionByCode($connection, array $payload): void
    {
        $fromCode = isset($payload['from_unit_code'])
            ? InventoryUnit::normalizeCode((string) $payload['from_unit_code'])
            : '';
        $toCode = isset($payload['to_unit_code'])
            ? InventoryUnit::normalizeCode((string) $payload['to_unit_code'])
            : '';

        if ($fromCode === '' || $toCode === '') {
            $fromId = (int) ($payload['from_unit_id'] ?? 0);
            $toId = (int) ($payload['to_unit_id'] ?? 0);
            if ($fromId > 0) {
                $fromCode = (string) $connection->table('inventory_units')->where('id', $fromId)->value('code');
            }
            if ($toId > 0) {
                $toCode = (string) $connection->table('inventory_units')->where('id', $toId)->value('code');
            }
        }

        $fromCode = InventoryUnit::normalizeCode($fromCode);
        $toCode = InventoryUnit::normalizeCode($toCode);

        if ($fromCode === '' || $toCode === '') {
            throw new \InvalidArgumentException('Conversion unit codes missing.');
        }

        $fromUnitId = (int) $connection->table('inventory_units')->where('code', $fromCode)->value('id');
        $toUnitId = (int) $connection->table('inventory_units')->where('code', $toCode)->value('id');

        if ($fromUnitId < 1 || $toUnitId < 1) {
            throw new \RuntimeException("Units not found for conversion: {$fromCode} → {$toCode}");
        }

        $row = [
            'from_unit_id' => $fromUnitId,
            'to_unit_id' => $toUnitId,
            'factor' => $payload['factor'] ?? 0,
            'note' => $payload['note'] ?? null,
            'updated_at' => $payload['updated_at'] ?? now(),
        ];

        $existing = $connection->table('inventory_unit_conversions')
            ->where('from_unit_id', $fromUnitId)
            ->where('to_unit_id', $toUnitId)
            ->first();

        if ($existing) {
            $connection->table('inventory_unit_conversions')
                ->where('id', $existing->id)
                ->update($row);
        } else {
            $row['created_at'] = $payload['created_at'] ?? now();
            $connection->table('inventory_unit_conversions')->insert($row);
        }
    }
}
