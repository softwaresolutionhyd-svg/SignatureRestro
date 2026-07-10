<?php

namespace App\Console\Commands;

use App\Models\InventoryUnit;
use App\Models\InventoryUnitConversion;
use App\Services\Sync\CloudSyncService;
use App\Services\Sync\SyncOutboxRecorder;
use Illuminate\Console\Command;

class SyncUomLibraryCommand extends Command
{
    protected $signature = 'sync:uom-library
                            {--queue-only : Sirf queue karo, push mat karo}';

    protected $description = 'Re-sync Units & conversion rules to hosting (by unit code, not row id)';

    public function handle(CloudSyncService $sync, SyncOutboxRecorder $recorder): int
    {
        if (! $sync->isLocalRole()) {
            $this->warn('SYNC_ROLE=local hona chahiye (cafe PC par chalao).');

            return self::FAILURE;
        }

        $units = 0;
        InventoryUnit::query()->orderBy('id')->each(function (InventoryUnit $unit) use ($recorder, &$units) {
            $recorder->recordModel($unit, 'upsert');
            $units++;
        });

        $rules = 0;
        InventoryUnitConversion::query()
            ->with(['fromUnit', 'toUnit'])
            ->orderBy('id')
            ->each(function (InventoryUnitConversion $conversion) use ($recorder, &$rules) {
                $recorder->recordModel($conversion, 'upsert');
                $rules++;
            });

        $this->info("Queued {$units} unit(s) and {$rules} conversion rule(s).");

        if ($this->option('queue-only')) {
            $this->line('Push ke liye: php artisan sync:cloud');

            return self::SUCCESS;
        }

        $result = $sync->push();
        $this->{$result['ok'] ? 'info' : 'error'}($result['message']);
        $this->line("Pushed: {$result['pushed']} | Pending: {$result['pending']}");

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
