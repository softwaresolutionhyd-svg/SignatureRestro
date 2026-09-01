<?php

namespace App\Console\Commands;

use App\Services\InventoryStockService;
use Illuminate\Console\Command;

class ReconcileBomDepartmentStockCommand extends Command
{
    protected $signature = 'inventory:reconcile-bom-departments';

    protected $description = 'Fix department shelf stock for past POS BoM sales that deducted ingredients from the wrong department';

    public function handle(InventoryStockService $stockService): int
    {
        $this->info('Reconciling BoM ingredient department stock…');
        $result = $stockService->reconcileMisallocatedBomDepartmentStock();
        $this->info(sprintf(
            'Done. Moves corrected: %d · skipped: %d',
            $result['fixed'],
            $result['skipped']
        ));

        return self::SUCCESS;
    }
}
