<?php

namespace App\Services;

use App\Models\InventoryCostLayer;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use App\Models\ManufacturingBom;
use App\Models\PurchaseOrder;
use App\Notifications\StockUpdated;
use App\Support\StaffNotifier;
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

        $touchedProductIds = [];

        DB::connection('tenant')->transaction(function () use ($order, &$touchedProductIds) {
            $order->load('lines');
            $warehouse = $this->inventoryStock->ensureWarehouse();

            foreach ($order->lines as $line) {
                /** @var InventoryProduct $product */
                $product = InventoryProduct::query()->lockForUpdate()->findOrFail($line->product_id);
                $before = (float) $product->qty_on_hand;

                $factor = $product->factorToBaseForUom((string) $line->uom);

                if ($factor === null || $factor <= 0) {
                    abort(422, "Invalid UOM '{$line->uom}' for product {$product->sku}.");
                }

                $qtyBase = (float) $line->qty * $factor;
                if ($qtyBase <= self::FIFO_EPSILON) {
                    continue;
                }

                $after = $before + $qtyBase;
                $product->update(['qty_on_hand' => $after]);

                $this->inventoryStock->addToWarehouse($product, $qtyBase);

                // Purchase rate converted to product base UOM (FIFO layer cost).
                $unitCostBase = round(((float) $line->unit_price) / $factor, 6);

                InventoryCostLayer::create([
                    'company_id' => $product->company_id,
                    'product_id' => $product->id,
                    'qty_remaining' => $qtyBase,
                    'unit_cost' => $unitCostBase,
                    'source' => 'purchase',
                    'reference' => $order->number,
                    'received_at' => now(),
                ]);

                InventoryMove::create([
                    'company_id' => $product->company_id,
                    'product_id' => $product->id,
                    'user_id' => auth()->id(),
                    'type' => 'in',
                    'from_department_id' => null,
                    'to_department_id' => $warehouse->id,
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

                // FIFO remaining-stock weighted cost → product.cost (auto).
                InventoryCostLayer::refreshProductUnitCost((int) $product->id, self::FIFO_EPSILON);
                $this->syncPurchaseProfit($product->fresh());

                $touchedProductIds[(int) $product->id] = true;

                $body = "{$product->sku} — {$product->name}: received {$line->qty} {$line->uom} (PO {$order->number})";
                StaffNotifier::notifyManagement(new StockUpdated([
                    'title' => 'Purchase received',
                    'body' => $body,
                    'product_id' => $product->id,
                    'type' => 'purchase_receive',
                    'reference' => $order->number,
                    'ts' => now()->toIso8601String(),
                ]), function_exists('current_company_id') ? current_company_id() : null);
            }

            $order->update([
                'status' => 'received',
                'received_at' => now(),
            ]);
        });

        // Recipes that use these ingredients: roll up new FIFO costs.
        foreach (array_keys($touchedProductIds) as $productId) {
            try {
                ManufacturingBom::syncFinishedProductsUsingComponent((int) $productId);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->autoJournal->postPurchaseReceived($order->fresh());
    }

    private function syncPurchaseProfit(InventoryProduct $product): void
    {
        if (! ($product->for_purchase ?? false)) {
            return;
        }

        $cost = round((float) $product->cost, 2);
        $price = round((float) $product->price, 2);
        $profit = round($price - $cost, 2);
        if (abs((float) $product->profit - $profit) >= 0.0000001) {
            $product->update(['profit' => $profit]);
        }
    }
}
