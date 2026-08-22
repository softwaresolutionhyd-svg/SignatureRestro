<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCostLayer extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'product_id',
        'qty_remaining',
        'unit_cost',
        'source',
        'reference',
        'received_at',
    ];

    protected $casts = [
        'qty_remaining' => 'decimal:3',
        'unit_cost' => 'decimal:6',
        'received_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    /**
     * Refresh product.cost from remaining FIFO layers.
     *
     * Uses value-weighted average of layers that still carry a real unit cost.
     * Zero-cost leftovers (old stock checks / mfg scraps) must not wipe purchase
     * cost used by BoM recipe costing.
     */
    public static function refreshProductUnitCost(int $productId, float $epsilon = 0.000001): float
    {
        $product = InventoryProduct::query()->find($productId);
        if (! $product) {
            return 0.0;
        }

        $layers = static::query()
            ->where('product_id', $productId)
            ->where('qty_remaining', '>', $epsilon)
            ->get(['qty_remaining', 'unit_cost']);

        $qty = 0.0;
        $value = 0.0;
        $qtyAll = 0.0;
        $valueAll = 0.0;

        foreach ($layers as $layer) {
            $remaining = (float) $layer->qty_remaining;
            if ($remaining <= $epsilon) {
                continue;
            }
            $unitCost = (float) $layer->unit_cost;
            $qtyAll += $remaining;
            $valueAll += $remaining * $unitCost;
            if ($unitCost > $epsilon) {
                $qty += $remaining;
                $value += $remaining * $unitCost;
            }
        }

        if ($qty > $epsilon) {
            $cost = round($value / $qty, 6);
        } elseif ($qtyAll > $epsilon) {
            $cost = round($valueAll / $qtyAll, 6);
        } else {
            $cost = 0.0;
        }

        if ($cost <= $epsilon && ($product->for_purchase ?? false)) {
            $fallback = static::lastPurchaseUnitCostBase($product, $epsilon);
            if ($fallback > $epsilon) {
                $cost = $fallback;
            }
        }

        if (abs((float) $product->cost - $cost) >= 0.0000001) {
            $product->cost = $cost;
            $product->save();
        }

        return $cost;
    }

    /**
     * When FIFO stock is fully consumed, keep the last known purchase rate on the product.
     */
    public static function lastPurchaseUnitCostBase(InventoryProduct $product, float $epsilon = 0.000001): float
    {
        $productId = (int) $product->id;

        $layerCost = static::query()
            ->where('product_id', $productId)
            ->where('unit_cost', '>', $epsilon)
            ->orderByRaw('COALESCE(received_at, created_at) DESC')
            ->orderByDesc('id')
            ->value('unit_cost');
        if ($layerCost !== null && (float) $layerCost > $epsilon) {
            return round((float) $layerCost, 6);
        }

        $moveCost = InventoryMove::query()
            ->where('product_id', $productId)
            ->where('type', 'in')
            ->where('unit_cost', '>', $epsilon)
            ->where('note', 'Received from vendor')
            ->orderByDesc('id')
            ->value('unit_cost');
        if ($moveCost !== null && (float) $moveCost > $epsilon) {
            return round((float) $moveCost, 6);
        }

        $line = PurchaseOrderLine::query()
            ->where('product_id', $productId)
            ->whereHas('order', fn ($q) => $q->where('status', 'received'))
            ->orderByDesc('id')
            ->first(['unit_price', 'uom']);

        if ($line) {
            $factor = $product->factorToBaseForUom((string) $line->uom);
            $factor = ($factor !== null && $factor > 0) ? $factor : 1.0;
            $base = (float) $line->unit_price / $factor;
            if ($base > $epsilon) {
                return round($base, 6);
            }
        }

        return 0.0;
    }
}
