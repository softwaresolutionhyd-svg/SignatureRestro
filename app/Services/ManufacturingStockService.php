<?php

namespace App\Services;

use App\Models\InventoryCostLayer;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use RuntimeException;

/**
 * Applies base-UOM stock movements for manufacturing completion.
 * Mirrors FIFO behaviour from Inventory MoveController (manufacturing-only entry point).
 */
final class ManufacturingStockService
{
    private const EPS = 0.000001;

    /**
     * @param  bool  $allowNegativeOnHand  POS: allow shelf qty below zero; FIFO shortfall valued at product cost. Manufacturing: keep false to block oversell.
     * @return float FIFO total cost absorbed for this consumption (for manufacturing rollup)
     */
    public function stockOut(
        InventoryProduct $product,
        float $qtyBase,
        ?int $userId,
        string $reference,
        ?string $note = null,
        bool $allowNegativeOnHand = false
    ): float {
        if ($qtyBase <= 0) {
            throw new RuntimeException('Quantity must be positive.');
        }

        $product = InventoryProduct::query()->lockForUpdate()->findOrFail($product->id);
        $before = (float) $product->qty_on_hand;
        if (! $allowNegativeOnHand && $qtyBase > $before) {
            throw new RuntimeException("Insufficient stock for {$product->sku}.");
        }

        $after = $before - $qtyBase;
        $product->update(['qty_on_hand' => $after]);

        [$unitCost, $totalCost] = $this->consumeFifo((int) $product->id, $qtyBase, $allowNegativeOnHand);
        $this->refreshProductCostFromLayers((int) $product->id);

        InventoryMove::create([
            'product_id' => $product->id,
            'user_id' => $userId,
            'type' => 'out',
            'qty' => $qtyBase,
            'uom' => $product->uom,
            'qty_uom' => $qtyBase,
            'factor_to_base' => 1,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'qty_before' => $before,
            'qty_after' => $after,
            'reference' => $reference,
            'note' => $note,
        ]);

        return (float) $totalCost;
    }

    /**
     * @param  float|null  $absorbedUnitCost  Sum of component FIFO costs ÷ output qty; overrides current product cost for the new layer.
     */
    public function stockIn(
        InventoryProduct $product,
        float $qtyBase,
        ?int $userId,
        string $reference,
        ?string $note = null,
        ?float $absorbedUnitCost = null
    ): void {
        if ($qtyBase <= 0) {
            throw new RuntimeException('Quantity must be positive.');
        }

        $product = InventoryProduct::query()->lockForUpdate()->findOrFail($product->id);
        $before = (float) $product->qty_on_hand;
        $after = $before + $qtyBase;
        $product->update(['qty_on_hand' => $after]);

        $layerCost = $absorbedUnitCost !== null
            ? round(max(0.0, $absorbedUnitCost), 6)
            : (float) $product->cost;

        InventoryCostLayer::create([
            'product_id' => $product->id,
            'qty_remaining' => $qtyBase,
            'unit_cost' => $layerCost,
            'source' => 'mfg',
            'reference' => $reference,
            'received_at' => now(),
        ]);
        $this->refreshProductCostFromLayers($product->id);

        InventoryMove::create([
            'product_id' => $product->id,
            'user_id' => $userId,
            'type' => 'in',
            'qty' => $qtyBase,
            'uom' => $product->uom,
            'qty_uom' => $qtyBase,
            'factor_to_base' => 1,
            'unit_cost' => $layerCost,
            'total_cost' => round($qtyBase * $layerCost, 6),
            'qty_before' => $before,
            'qty_after' => $after,
            'reference' => $reference,
            'note' => $note,
        ]);
    }

    private function consumeFifo(int $productId, float $qtyBase, bool $allowLayerShortfall = false): array
    {
        $product = InventoryProduct::query()->findOrFail($productId);
        if (InventoryCostLayer::query()->where('product_id', $productId)->doesntExist() && (float) $product->qty_on_hand > 0) {
            InventoryCostLayer::create([
                'product_id' => $productId,
                'qty_remaining' => (float) $product->qty_on_hand + $qtyBase,
                'unit_cost' => (float) $product->cost,
                'source' => 'opening',
                'reference' => 'opening',
                'received_at' => now()->subSecond(),
            ]);
        }

        $remaining = $qtyBase;
        $costTotal = 0.0;

        $layers = InventoryCostLayer::query()
            ->where('product_id', $productId)
            ->where('qty_remaining', '>', 0)
            ->orderByRaw('COALESCE(received_at, created_at) asc')
            ->lockForUpdate()
            ->get();

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, (float) $layer->qty_remaining);
            $costTotal += $take * (float) $layer->unit_cost;
            $newRemaining = (float) $layer->qty_remaining - $take;
            if (abs($newRemaining) < self::EPS) {
                $newRemaining = 0.0;
            }
            $layer->update(['qty_remaining' => $newRemaining]);
            $remaining -= $take;
        }

        if ($remaining > 0.0000001) {
            if (! $allowLayerShortfall) {
                throw new RuntimeException('FIFO layers insufficient for component consumption.');
            }
            $product = InventoryProduct::query()->findOrFail($productId);
            $fallback = (float) $product->cost;
            $costTotal += $remaining * $fallback;
            $remaining = 0.0;
        }

        $unitCostWeighted = $qtyBase > 0 ? ($costTotal / $qtyBase) : null;

        return [$unitCostWeighted, $costTotal];
    }

    private function refreshProductCostFromLayers(int $productId): void
    {
        $product = InventoryProduct::query()->find($productId);
        if (!$product) {
            return;
        }

        $layer = InventoryCostLayer::query()
            ->where('product_id', $productId)
            ->where('qty_remaining', '>', self::EPS)
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
