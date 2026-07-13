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

        try {
            $response = Http::timeout(8)
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
    public function push(): array
    {
        if (! $this->isLocalRole()) {
            return ['ok' => false, 'pushed' => 0, 'pending' => 0, 'message' => 'Sync only runs on local role.'];
        }

        $url = config('sync.remote_url');
        $token = config('sync.token');

        if ($url === '' || $token === '') {
            return ['ok' => false, 'pushed' => 0, 'pending' => $this->pendingCount(), 'message' => 'SYNC_REMOTE_URL / SYNC_TOKEN missing.'];
        }

        $batchSize = max(1, (int) config('sync.batch_size', 100));
        $pushed = 0;

        $this->schemaService->ensureAll();

        try {
            Http::timeout(8)
                ->withToken($token)
                ->acceptJson()
                ->get($url.'/api/sync/ping');
        } catch (Throwable) {
            // hosting may be on older build; push will still attempt
        }

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
                $response = Http::timeout(60)
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
        ];
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
