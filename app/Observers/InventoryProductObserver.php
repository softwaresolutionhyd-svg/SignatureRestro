<?php

namespace App\Observers;

use App\Models\InventoryProduct;
use App\Models\ManufacturingBom;

class InventoryProductObserver
{
    public function updated(InventoryProduct $product): void
    {
        if (!$product->wasChanged('cost')) {
            return;
        }

        try {
            // BoM roll-up is secondary; it must not block stock receive/sale flows.
            ManufacturingBom::syncFinishedProductsUsingComponent((int) $product->id);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
