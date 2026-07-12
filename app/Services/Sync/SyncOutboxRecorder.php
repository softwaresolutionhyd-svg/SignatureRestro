<?php

namespace App\Services\Sync;

use App\Models\InventoryUnit;
use App\Models\InventoryUnitConversion;
use App\Models\SyncQueueItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SyncOutboxRecorder
{
    public static bool $applyingRemote = false;

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

        if (! Schema::hasTable('sync_queue')) {
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

        return $payload;
    }
}
