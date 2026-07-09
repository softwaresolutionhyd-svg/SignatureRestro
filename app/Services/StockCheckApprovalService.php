<?php

namespace App\Services;

use App\Models\InventoryCostLayer;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use App\Models\StockCheck;
use App\Models\StockCheckLine;
use App\Models\User;
use App\Notifications\StockUpdated;
use Illuminate\Support\Facades\DB;

/**
 * Approve: set on-hand to physical counted qty (base UOM) using in/out + FIFO (same rules as manual moves).
 */
final class StockCheckApprovalService
{
    private const EPS = 0.000001;

    public function approve(StockCheck $check, int $approverUserId): void
    {
        abort_unless($check->status === 'pending_approval', 422);

        DB::connection('tenant')->transaction(function () use ($check, $approverUserId) {
            $check->load('lines.product');

            foreach ($check->lines as $line) {
                $this->applyLine($check, $line, $approverUserId);
            }

            $check->update([
                'status' => 'approved',
                'reviewed_by' => $approverUserId,
                'reviewed_at' => now(),
                'reject_reason' => null,
            ]);
        });
    }

    private function applyLine(StockCheck $check, StockCheckLine $line, int $actorUserId): void
    {
        if ($line->counted_qty === null) {
            return;
        }

        /** @var InventoryProduct $product */
        $product = InventoryProduct::query()->lockForUpdate()->findOrFail($line->product_id);
        $before = (float) $product->qty_on_hand;
        $target = (float) $line->counted_qty;
        $delta = $target - $before;

        if (abs($delta) < self::EPS) {
            return;
        }

        $baseUom = (string) $product->uom;
        $ref = $check->number;

        if ($delta > 0) {
            $product->update(['qty_on_hand' => $target]);
            $layerCost = (float) $product->cost;
            InventoryCostLayer::create([
                'product_id' => $product->id,
                'qty_remaining' => $delta,
                'unit_cost' => $layerCost,
                'source' => 'stock_check',
                'reference' => $ref,
                'received_at' => now(),
            ]);
            $this->refreshProductCostFromLayers($product->id);

            InventoryMove::create([
                'product_id' => $product->id,
                'user_id' => $actorUserId,
                'type' => 'in',
                'qty' => $delta,
                'uom' => $baseUom,
                'qty_uom' => $delta,
                'factor_to_base' => 1.0,
                'unit_cost' => $layerCost,
                'total_cost' => $layerCost * $delta,
                'qty_before' => $before,
                'qty_after' => $target,
                'reference' => $ref,
                'note' => 'Stock check approve (+'.fmt_num($delta, 4).' '.$baseUom.')',
            ]);
        } else {
            $out = abs($delta);
            if ($out > $before + self::EPS) {
                $hasActiveBom = $product->manufacturingBoms()->where('active', true)->exists();
                if (! $hasActiveBom) {
                    abort(422, "Insufficient stock to apply count for {$product->sku} (need to remove ".fmt_num($out, 4).', have '.fmt_num($before, 4).').');
                }
            }

            [$unitCost, $totalCost] = $this->consumeFifo($product->id, $out);
            $product->update(['qty_on_hand' => $target]);
            $this->refreshProductCostFromLayers($product->id);

            InventoryMove::create([
                'product_id' => $product->id,
                'user_id' => $actorUserId,
                'type' => 'out',
                'qty' => $out,
                'uom' => $baseUom,
                'qty_uom' => $out,
                'factor_to_base' => 1.0,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'qty_before' => $before,
                'qty_after' => $target,
                'reference' => $ref,
                'note' => 'Stock check approve (−'.fmt_num($out, 4).' '.$baseUom.')',
            ]);
        }

        $body = "{$product->sku} — {$product->name}: stock check {$check->number} → on-hand ".fmt_num($target, 4).' '.$baseUom;
        User::query()->select(['id'])->each(function ($u) use ($body, $product, $ref) {
            $u->notify(new StockUpdated([
                'title' => 'Stock check applied',
                'body' => $body,
                'product_id' => $product->id,
                'type' => 'stock_check',
                'reference' => $ref,
                'ts' => now()->toIso8601String(),
            ]));
        });
    }

    private function consumeFifo(int $productId, float $qtyBase): array
    {
        $product = InventoryProduct::query()->findOrFail($productId);
        if (InventoryCostLayer::query()->where('product_id', $productId)->doesntExist() && (float) $product->qty_on_hand > 0) {
            InventoryCostLayer::create([
                'product_id' => $productId,
                'qty_remaining' => (float) $product->qty_on_hand,
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
            abort(422, 'FIFO layers insufficient for stock check adjustment.');
        }

        $unitCostWeighted = $qtyBase > 0 ? ($costTotal / $qtyBase) : null;

        return [$unitCostWeighted, $costTotal];
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
            ->first();

        if ($layer) {
            $product->cost = (float) $layer->unit_cost;
            $product->save();

            return;
        }

        $product->cost = 0;
        $product->save();
    }
}
