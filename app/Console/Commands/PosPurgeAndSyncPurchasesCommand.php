<?php

namespace App\Console\Commands;

use App\Models\InventoryCostLayer;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use App\Models\PurchaseOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deletes all POS records and rebuilds on-hand qty + FIFO cost layers strictly from
 * received purchase orders. Also clears all inventory_moves (POS, purchases, adjustments,
 * manufacturing) so the movement log matches the rebuilt stock.
 */
class PosPurgeAndSyncPurchasesCommand extends Command
{
    protected $signature = 'pos:purge-and-sync-purchases {--force : Skip confirmation prompt}';

    protected $description = 'Remove all POS data; wipe inventory moves & cost layers; set qty and FIFO layers from received POs only';

    private const EPS = 0.000001;

    public function handle(): int
    {
        if (! $this->option('force')) {
            if ($this->input->isInteractive()) {
                if (! $this->confirm(
                    'This deletes ALL POS sessions/orders, ALL inventory_moves, ALL cost layers, and sets stock only from received purchase orders. Continue?'
                )) {
                    return self::FAILURE;
                }
            } else {
                $this->error('Non-interactive environment: re-run with --force to execute the purge.');

                return self::FAILURE;
            }
        }

        if (! Schema::connection('tenant')->hasTable('inventory_products')) {
            $this->error('Table inventory_products not found.');

            return self::FAILURE;
        }

        try {
            DB::connection('tenant')->transaction(function () {
                $this->purgeAllPosData();

                $nm = DB::connection('tenant')->table('inventory_moves')->delete();
                $this->line("Removed {$nm} inventory move row(s).");

                $nl = DB::connection('tenant')->table('inventory_cost_layers')->delete();
                $this->line("Removed {$nl} cost layer row(s).");

                InventoryProduct::query()->update(['qty_on_hand' => 0]);

                $orders = PurchaseOrder::query()
                    ->where('status', 'received')
                    ->with([
                        'lines' => fn ($q) => $q->orderBy('id'),
                    ])
                    ->orderByRaw('COALESCE(received_at, confirmed_at, created_at) asc')
                    ->orderBy('id')
                    ->get();

                $skipped = 0;
                foreach ($orders as $order) {
                    $receivedAt = $order->received_at ?? $order->confirmed_at ?? $order->created_at;

                    foreach ($order->lines as $line) {
                        $product = InventoryProduct::query()
                            ->with(['uomConversions' => fn ($q) => $q->where('active', true)])
                            ->lockForUpdate()
                            ->find($line->product_id);

                        if (! $product) {
                            $this->warn("Skipping PO line {$line->id}: product {$line->product_id} missing.");
                            $skipped++;

                            continue;
                        }

                        $factor = $product->factorToBaseForUom((string) $line->uom);
                        if ($factor === null || $factor <= 0) {
                            $this->warn("Skipping PO line {$line->id}: invalid UOM \"{$line->uom}\" for {$product->sku}.");
                            $skipped++;

                            continue;
                        }

                        $qtyBase = (float) $line->qty * $factor;
                        $before = (float) $product->qty_on_hand;
                        $after = $before + $qtyBase;

                        $unitCostBase = $factor > 0 ? ((float) $line->unit_price / $factor) : (float) $line->unit_price;

                        $product->update(['qty_on_hand' => $after]);

                        InventoryCostLayer::create([
                            'company_id' => $product->company_id,
                            'product_id' => $product->id,
                            'qty_remaining' => $qtyBase,
                            'unit_cost' => $unitCostBase,
                            'source' => 'purchase',
                            'reference' => $order->number,
                            'received_at' => $receivedAt,
                        ]);

                        InventoryMove::create([
                            'company_id' => $product->company_id,
                            'product_id' => $product->id,
                            'user_id' => null,
                            'type' => 'in',
                            'qty' => $qtyBase,
                            'uom' => $line->uom,
                            'qty_uom' => (float) $line->qty,
                            'factor_to_base' => $factor,
                            'unit_cost' => $unitCostBase,
                            'total_cost' => round($unitCostBase * $qtyBase, 6),
                            'qty_before' => $before,
                            'qty_after' => $after,
                            'reference' => $order->number,
                            'note' => 'Received from vendor',
                        ]);
                    }
                }

                foreach (InventoryProduct::query()->pluck('id') as $pid) {
                    $this->refreshProductCostFromLayers((int) $pid);
                }

                if ($skipped > 0) {
                    $this->warn("Skipped {$skipped} purchase line(s); their qty was not applied.");
                }
            });
        } catch (\Throwable $e) {
            $this->error('Purge failed (transaction rolled back): '.$e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }

        $this->info('POS purge and purchase-based stock rebuild finished.');

        return self::SUCCESS;
    }

    /**
     * Explicit deletes + foreign key relax (MySQL/SQLite) so rows are removed even when ON DELETE CASCADE
     * is missing or disabled in the DB.
     */
    private function purgeAllPosData(): void
    {
        if (! Schema::connection('tenant')->hasTable('pos_orders') && ! Schema::connection('tenant')->hasTable('pos_sessions')) {
            return;
        }

        Schema::connection('tenant')->withoutForeignKeyConstraints(function () {
            if (Schema::connection('tenant')->hasTable('credit_ledger')) {
                $n = DB::connection('tenant')->table('credit_ledger')->whereNotNull('pos_order_id')->delete();
                $this->line("Removed {$n} credit ledger row(s) tied to POS orders.");
            }

            if (Schema::connection('tenant')->hasTable('pos_payments')) {
                $n = DB::connection('tenant')->table('pos_payments')->delete();
                $this->line("Removed {$n} POS payment row(s).");
            }

            if (Schema::connection('tenant')->hasTable('pos_order_items')) {
                $n = DB::connection('tenant')->table('pos_order_items')->delete();
                $this->line("Removed {$n} POS order item row(s).");
            }

            if (Schema::connection('tenant')->hasTable('pos_orders')) {
                $n = DB::connection('tenant')->table('pos_orders')->delete();
                $this->line("Removed {$n} POS order row(s).");
            }

            if (Schema::connection('tenant')->hasTable('pos_cash_movements')) {
                $n = DB::connection('tenant')->table('pos_cash_movements')->delete();
                $this->line("Removed {$n} POS cash movement row(s).");
            }

            if (Schema::connection('tenant')->hasTable('pos_sessions')) {
                $n = DB::connection('tenant')->table('pos_sessions')->delete();
                $this->line("Removed {$n} POS session row(s).");
            }
        });
    }

    private function refreshProductCostFromLayers(int $productId): void
    {
        $product = InventoryProduct::query()->find($productId);
        if (! $product) {
            return;
        }

        $layer = InventoryCostLayer::query()
            ->where('product_id', $productId)
            ->where('qty_remaining', '>', self::EPS)
            ->orderByRaw('COALESCE(received_at, created_at) asc')
            ->orderBy('id')
            ->first();

        if ($layer) {
            $product->cost = (float) $layer->unit_cost;
        } else {
            $product->cost = 0;
        }

        $product->save();
    }
}
