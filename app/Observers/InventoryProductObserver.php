<?php

namespace App\Observers;

use App\Models\InventoryProduct;
use App\Models\ManufacturingBom;
use Illuminate\Support\Facades\Cache;

class InventoryProductObserver
{
    public function saved(InventoryProduct $product): void
    {
        $this->forgetOrderTakerProductCache();
    }

    public function deleted(InventoryProduct $product): void
    {
        $this->forgetOrderTakerProductCache();
    }

    public function updated(InventoryProduct $product): void
    {
        if (! $product->wasChanged('cost')) {
            return;
        }

        try {
            // BoM roll-up is secondary; it must not block stock receive/sale flows.
            ManufacturingBom::syncFinishedProductsUsingComponent((int) $product->id);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function forgetOrderTakerProductCache(): void
    {
        try {
            $companyId = function_exists('current_company_id') ? (current_company_id() ?? 0) : 0;
            Cache::forget('order_taker:pos_products:c'.$companyId);
            Cache::forget('order_taker:api_products:c'.$companyId);
        } catch (\Throwable) {
            // ignore
        }
    }
}
