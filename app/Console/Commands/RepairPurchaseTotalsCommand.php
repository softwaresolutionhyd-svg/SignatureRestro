<?php

namespace App\Console\Commands;

use App\Services\PurchaseTotalsReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RepairPurchaseTotalsCommand extends Command
{
    protected $signature = 'purchase:recalc-totals {--force : Run even if already repaired}';

    protected $description = 'Recalc purchase line unit prices from stored invoice totals and fix header subtotals.';

    public function handle(PurchaseTotalsReconciler $reconciler): int
    {
        if ($this->option('force')) {
            Cache::forget(PurchaseTotalsReconciler::SETTING_KEY.':tenant');
        }

        $this->info('Widening unit_price and repairing all purchase orders…');
        $result = $reconciler->repairAll();
        Cache::forever(PurchaseTotalsReconciler::SETTING_KEY.':tenant', '1');
        try {
            \App\Models\Setting::set(PurchaseTotalsReconciler::SETTING_KEY, '1');
        } catch (\Throwable) {
        }
        $this->info(sprintf(
            'Done. Orders touched: %d · lines updated: %d · headers updated: %d',
            $result['orders'],
            $result['lines'],
            $result['headers']
        ));

        return self::SUCCESS;
    }
}
