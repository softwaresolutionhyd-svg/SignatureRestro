<?php

namespace App\Services\Sync;

use App\Models\Employee;
use App\Models\EmployeeStaffCategory;
use App\Models\InventoryUnit;
use App\Models\InventoryUnitConversion;
use App\Models\SyncQueueItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SyncOutboxRecorder
{
    public static bool $applyingRemote = false;

    private static ?bool $syncQueueTableExists = null;

    public function shouldRecord(): bool
    {
        if (self::$applyingRemote) {
            return false;
        }

        if (! config('sync.enabled')) {
            return false;
        }

        return config('sync.role') === 'local';
    }

    public function recordModel(Model $model, string $action): void
    {
        if (! $this->shouldRecord()) {
            return;
        }

        $table = $model->getTable();
        if ($this->isExcluded($table)) {
            return;
        }

        if (! $this->syncQueueExists()) {
            return;
        }

        $key = (string) $model->getKey();
        if ($key === '') {
            return;
        }

        $payload = null;
        if ($action !== 'delete') {
            $payload = $model->getAttributes();
            foreach ($payload as $k => $v) {
                if ($v instanceof \DateTimeInterface) {
                    $payload[$k] = $v->format('Y-m-d H:i:s');
                } elseif (is_object($v)) {
                    $payload[$k] = (string) $v;
                }
            }
            $payload = $this->enrichPayload($table, $payload, $model);
        }

        // Collapse rapid edits of the same row into one pending item.
        $pending = SyncQueueItem::query()
            ->whereNull('synced_at')
            ->where('table_name', $table)
            ->where('record_key', $key)
            ->orderByDesc('id')
            ->first();

        if ($pending) {
            $pending->fill([
                'action' => $action,
                'payload' => $payload,
                'last_error' => null,
            ])->save();

            app(SyncPushScheduler::class)->schedule();

            return;
        }

        SyncQueueItem::query()->create([
            'table_name' => $table,
            'record_key' => $key,
            'action' => $action,
            'payload' => $payload,
        ]);

        app(SyncPushScheduler::class)->schedule();
    }

    /** Queue delete for a table row by primary key (when Eloquent events will not fire). */
    public function recordDeleteKey(string $table, string|int $key): void
    {
        if (! $this->shouldRecord() || $this->isExcluded($table) || ! $this->syncQueueExists()) {
            return;
        }

        $key = (string) $key;
        if ($key === '') {
            return;
        }

        $this->writeQueueItem($table, $key, 'delete', null);
    }

    /**
     * Queue upsert for a raw table row (pivots / Query Builder writes).
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordUpsertRow(string $table, string|int $key, array $payload): void
    {
        if (! $this->shouldRecord() || $this->isExcluded($table) || ! $this->syncQueueExists()) {
            return;
        }

        $key = (string) $key;
        if ($key === '') {
            return;
        }

        foreach ($payload as $k => $v) {
            if ($v instanceof \DateTimeInterface) {
                $payload[$k] = $v->format('Y-m-d H:i:s');
            } elseif (is_object($v)) {
                $payload[$k] = (string) $v;
            }
        }

        $this->writeQueueItem($table, $key, 'upsert', $payload);
    }

    /**
     * After a BelongsToMany::sync(), re-queue all pivot rows for the parent so cloud matches.
     *
     * @param  list<int|string>  $oldPivotIds
     */
    public function resyncPivotTable(string $table, string $foreignKey, int|string $foreignId, array $oldPivotIds = []): void
    {
        if (! $this->shouldRecord() || $this->isExcluded($table) || ! $this->syncQueueExists()) {
            return;
        }

        if (! Schema::hasTable($table)) {
            return;
        }

        $newRows = \Illuminate\Support\Facades\DB::table($table)
            ->where($foreignKey, $foreignId)
            ->get();

        $newIds = $newRows->pluck('id')->map(fn ($id) => (string) $id)->all();
        foreach ($oldPivotIds as $oldId) {
            $oldId = (string) $oldId;
            if ($oldId !== '' && ! in_array($oldId, $newIds, true)) {
                $this->recordDeleteKey($table, $oldId);
            }
        }

        foreach ($newRows as $row) {
            $attrs = (array) $row;
            $id = $attrs['id'] ?? null;
            if ($id === null) {
                continue;
            }
            $this->recordUpsertRow($table, $id, $attrs);
        }
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function writeQueueItem(string $table, string $key, string $action, ?array $payload): void
    {
        $pending = SyncQueueItem::query()
            ->whereNull('synced_at')
            ->where('table_name', $table)
            ->where('record_key', $key)
            ->orderByDesc('id')
            ->first();

        if ($pending) {
            $pending->fill([
                'action' => $action,
                'payload' => $payload,
                'last_error' => null,
            ])->save();

            app(SyncPushScheduler::class)->schedule();

            return;
        }

        SyncQueueItem::query()->create([
            'table_name' => $table,
            'record_key' => $key,
            'action' => $action,
            'payload' => $payload,
        ]);

        app(SyncPushScheduler::class)->schedule();
    }

    public function isExcluded(string $table): bool
    {
        return in_array($table, config('sync.exclude_tables', []), true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function enrichPayload(string $table, array $payload, ?Model $model = null): array
    {
        if ($table === 'inventory_unit_conversions') {
            if ($model instanceof InventoryUnitConversion) {
                $model->loadMissing(['fromUnit', 'toUnit']);
                $payload['from_unit_code'] = $model->fromUnit?->code;
                $payload['to_unit_code'] = $model->toUnit?->code;
            } elseif (! empty($payload['from_unit_id']) || ! empty($payload['to_unit_id'])) {
                $from = InventoryUnit::query()->find($payload['from_unit_id'] ?? 0);
                $to = InventoryUnit::query()->find($payload['to_unit_id'] ?? 0);
                $payload['from_unit_code'] = $from?->code;
                $payload['to_unit_code'] = $to?->code;
            }
        }

        if ($table === 'employees' && ! empty($payload['staff_category_id'])) {
            if ($model instanceof Employee) {
                $model->loadMissing('staffCategory:id,slug');
                $payload['staff_category_slug'] = $model->staffCategory?->slug;
            } else {
                $cat = EmployeeStaffCategory::query()->find($payload['staff_category_id']);
                $payload['staff_category_slug'] = $cat?->slug;
            }
        }

        if (in_array($table, ['inventory_products', 'settings'], true)) {
            $payload = SyncMediaFiles::attachToPayload($table, $payload);
        }

        return $payload;
    }

    private function syncQueueExists(): bool
    {
        if (self::$syncQueueTableExists !== null) {
            return self::$syncQueueTableExists;
        }

        self::$syncQueueTableExists = Schema::hasTable('sync_queue');

        return self::$syncQueueTableExists;
    }
}
