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
        protected SyncOutboxRecorder $recorder
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

                $item->forceFill([
                    'attempts' => min(254, (int) $item->attempts + 1),
                    'last_error' => $errorText,
                ])->save();
            }

            if ($failed->isNotEmpty()) {
                break;
            }
        }

        $this->setMeta('last_push_at', now()->toIso8601String());
        $this->setMeta('last_push_ok', '1');

        return [
            'ok' => true,
            'pushed' => $pushed,
            'pending' => $this->pendingCount(),
            'message' => $pushed > 0 ? "Synced {$pushed} change(s) to hosting." : 'Already up to date.',
        ];
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
                        } else {
                            unset($payload['id']);
                            $connection->table($table)->updateOrInsert(['id' => $key], $payload);
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
