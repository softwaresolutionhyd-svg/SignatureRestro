<?php

namespace App\Support;

use App\Models\InventoryProduct;

/** Placeholder catalog SKU for free-text POS “On Demand” lines (no stock). */
final class PosCustomProduct
{
    public const SKU = 'POS-CUSTOM';

    public const NAME = 'On Demand';

    public static function ensure(): InventoryProduct
    {
        $existing = InventoryProduct::query()
            ->where('sku', self::SKU)
            ->first();

        if ($existing) {
            $dirty = false;
            if (! $existing->for_pos) {
                $existing->for_pos = true;
                $dirty = true;
            }
            if ($existing->for_purchase) {
                $existing->for_purchase = false;
                $dirty = true;
            }
            if (! $existing->active) {
                $existing->active = true;
                $dirty = true;
            }
            if (strcasecmp((string) $existing->uom, 'unit') !== 0) {
                $existing->uom = 'unit';
                $dirty = true;
            }
            if ($dirty) {
                $existing->save();
            }

            return $existing;
        }

        return InventoryProduct::query()->create([
            'category_id' => null,
            'sku' => self::SKU,
            'name' => self::NAME,
            'uom' => 'unit',
            'cost' => 0,
            'price' => 0,
            'qty_on_hand' => 0,
            'reorder_level' => 0,
            'active' => true,
            'for_pos' => true,
            'for_purchase' => false,
            'extra_costs' => [],
            'gas_charges' => 0,
            'service_charges' => 0,
            'profit' => 0,
        ]);
    }

    public static function isCustomSku(?string $sku): bool
    {
        return strcasecmp(trim((string) $sku), self::SKU) === 0;
    }
}
