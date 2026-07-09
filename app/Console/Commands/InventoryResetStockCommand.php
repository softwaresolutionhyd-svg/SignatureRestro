<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sets every product qty_on_hand to 0 and clears FIFO cost layers + inventory move log
 * so stock position matches a clean slate.
 */
class InventoryResetStockCommand extends Command
{
    protected $signature = 'inventory:reset-stock
        {--force : Skip confirmation (required in non-interactive mode)}
        {--company= : If set, only rows for this company_id (inventory_products.company_id)}';

    protected $description = 'Set all products qty_on_hand to 0; delete inventory moves, cost layers, and purchase history';

    public function handle(): int
    {
        if (! $this->option('force')) {
            if ($this->input->isInteractive()) {
                if (! $this->confirm(
                    'This will DELETE purchase_orders(+lines), inventory_moves, inventory_cost_layers, and set qty_on_hand = 0 for every product. Continue?'
                )) {
                    return self::FAILURE;
                }
            } else {
                $this->error('Non-interactive: run with --force to confirm.');

                return self::FAILURE;
            }
        }

        $conn = DB::connection('tenant');

        if (! Schema::connection('tenant')->hasTable('inventory_products')) {
            $this->error('Table inventory_products not found on tenant connection.');

            return self::FAILURE;
        }

        $companyId = $this->option('company');
        $companyId = $companyId !== null && $companyId !== '' ? (int) $companyId : null;

        try {
            $conn->transaction(function () use ($conn, $companyId) {
                $hasCompany = Schema::connection('tenant')->hasColumn('inventory_products', 'company_id');

                $productScope = function ($q) use ($companyId, $hasCompany) {
                    if ($companyId !== null && $hasCompany) {
                        $q->where('company_id', $companyId);
                    }
                };

                if (Schema::connection('tenant')->hasTable('inventory_moves')) {
                    $q = $conn->table('inventory_moves');
                    if ($companyId !== null && Schema::connection('tenant')->hasColumn('inventory_moves', 'company_id')) {
                        $q->where('company_id', $companyId);
                    }
                    $nm = $q->delete();
                    $this->line("Removed {$nm} inventory move row(s).");
                }

                if (Schema::connection('tenant')->hasTable('purchase_orders')) {
                    $q = $conn->table('purchase_orders');
                    if ($companyId !== null && Schema::connection('tenant')->hasColumn('purchase_orders', 'company_id')) {
                        $q->where('company_id', $companyId);
                    }
                    $npo = $q->delete();
                    $this->line("Removed {$npo} purchase order row(s) (lines cascade-deleted).");
                }

                if (Schema::connection('tenant')->hasTable('inventory_cost_layers')) {
                    $q = $conn->table('inventory_cost_layers');
                    if ($companyId !== null && Schema::connection('tenant')->hasColumn('inventory_cost_layers', 'company_id')) {
                        $q->where('company_id', $companyId);
                    }
                    $nl = $q->delete();
                    $this->line("Removed {$nl} cost layer row(s).");
                }

                $q = $conn->table('inventory_products');
                $productScope($q);
                $np = $q->update(['qty_on_hand' => 0]);
                $this->info("Set qty_on_hand = 0 on {$np} product row(s).");
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->warn('Product unit costs (cost field) were not changed. If you need cost from FIFO only, adjust manually or receive stock again.');

        return self::SUCCESS;
    }
}
