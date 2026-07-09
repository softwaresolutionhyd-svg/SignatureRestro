<?php

namespace App\Services;

use App\Models\InventoryCostLayer;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Notifications\StockUpdated;
use Illuminate\Support\Facades\DB;

final class PurchaseOrderReceiveService
{
    private const FIFO_EPSILON = 0.000001;

    public function __construct(
        private readonly AutoJournalService $autoJournal,
        private readonly InventoryStockService $inventoryStock,
    ) {}

    public function receive(PurchaseOrder $order): void
    {
        abort_unless($order->status === 'confirmed', 403);

        DB::connection('tenant')->transaction(function () use ($order) {
            $order->load('lines');

            foreach ($order->lines as $line) {
                /** @var InventoryProduct $product */
                $product = InventoryProduct::query()->lockForUpdate()->findOrFail($line->product_id);
                $before = (float) $product->qty_on_hand;

                $factor = $product->factorToBaseForUom((string) $line->uom);

                if ($factor === null || $factor <= 0) {
                    abort(422, "Invalid UOM '{$line->uom}' for product {$product->sku}.");
                }

                $qtyBase = (float) $line->qty * $factor;
                $after = $before + $qtyBase;

                $product->update(['qty_on_hand' => $after]);

                $this->inventoryStock->addToWarehouse($product, $qtyBase);

                $unitCostBase = $factor > 0 ? ((float) $line->unit_price / $factor) : (float) $line->unit_price;
                InventoryCostLayer::create([
                    'product_id' => $product->id,
                    'qty_remaining' => $qtyBase,
                    'unit_cost' => $unitCostBase,
                    'source' => 'purchase',
                    'reference' => $order->number,
                    'received_at' => now(),
                ]);

                InventoryMove::create([
                    'product_id' => $product->id,
                    'user_id' => auth()->id(),
                    'type' => 'in',
                    'qty' => $qtyBase,
                    'uom' => $line->uom,
                    'qty_uom' => (float) $line->qty,
                    'factor_to_base' => $factor,
                    'unit_cost' => $unitCostBase,
                    'total_cost' => $unitCostBase * $qtyBase,
                    'qty_before' => $before,
                    'qty_after' => $after,
                    'reference' => $order->number,
                    'note' => 'Received from vendor',
                ]);

                $body = "{$product->sku} — {$product->name}: received {$line->qty} {$line->uom} (PO {$order->number})";
                User::query()->select(['id'])->each(function ($u) use ($body, $product, $order) {
                    $u->notify(new StockUpdated([
                        'title' => 'Purchase received',
                        'body' => $body,
                        'product_id' => $product->id,
                        'type' => 'purchase_receive',
                        'reference' => $order->number,
                        'ts' => now()->toIso8601String(),
                    ]));
                });
            }

            $this->refreshProductCostsFromLayers($order->lines->pluck('product_id')->unique()->all());

            $order->update([
                'status' => 'received',
                'received_at' => now(),
            ]);
        });

        $this->autoJournal->postPurchaseReceived($order->fresh());
    }

    /**
     * @param  list<int|string>  $productIds
     */
    private function refreshProductCostsFromLayers(array $productIds): void
    {
        foreach ($productIds as $pid) {
            $product = InventoryProduct::query()->find($pid);
            if (! $product) {
                continue;
            }

            $layer = InventoryCostLayer::query()
                ->where('product_id', $pid)
                ->where('qty_remaining', '>', self::FIFO_EPSILON)
                ->orderByRaw('COALESCE(received_at, created_at) asc')
                ->first();

            if ($layer) {
                $product->cost = (float) $layer->unit_cost;
            } else {
                $product->cost = 0;
            }

            $product->save();
        }
    }
}
