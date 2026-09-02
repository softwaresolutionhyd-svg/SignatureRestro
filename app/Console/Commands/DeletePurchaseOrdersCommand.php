<?php

namespace App\Console\Commands;

use App\Models\CreditLedger;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\Sync\SyncAwareDelete;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeletePurchaseOrdersCommand extends Command
{
    protected $signature = 'purchase:delete {numbers* : PO numbers e.g. PO00149 PO00150}';

    protected $description = 'Delete RFQ/confirmed (not received) purchase orders and related lines/ledger rows';

    public function handle(): int
    {
        $numbers = collect($this->argument('numbers'))
            ->flatMap(fn ($n) => preg_split('/[\s,]+/', (string) $n, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($n) => strtoupper(trim((string) $n)))
            ->filter()
            ->unique()
            ->values();

        if ($numbers->isEmpty()) {
            $this->error('Provide at least one PO number.');

            return self::FAILURE;
        }

        $deleted = 0;

        foreach ($numbers as $number) {
            $order = PurchaseOrder::query()->where('number', $number)->first();
            if ($order === null) {
                $this->warn("{$number}: not found.");

                continue;
            }

            if (! in_array($order->status, ['rfq', 'confirmed'], true)) {
                $this->warn("{$number}: status is {$order->status}; only RFQ/confirmed can be deleted.");

                continue;
            }

            DB::connection('tenant')->transaction(function () use ($order) {
                SyncAwareDelete::query(
                    CreditLedger::query()->where('purchase_order_id', $order->id)
                );
                SyncAwareDelete::query(
                    PurchaseOrderLine::query()->where('purchase_order_id', $order->id)
                );
                $order->delete();
            });

            $this->info("Deleted {$number}.");
            $deleted++;
        }

        $this->info("Done. Deleted {$deleted} PO(s).");

        return self::SUCCESS;
    }
}
