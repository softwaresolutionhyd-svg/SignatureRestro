<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sets every product qty_on_hand to 0 and clears FIFO cost layers + inventory move log
 * so stock position matches a clean slate. Deletes purchase history. Keeps products.
 */
class InventoryResetStockCommand extends Command
{
    protected $signature = 'inventory:reset-stock
        {--force : Skip confirmation (required in non-interactive mode)}
        {--company= : If set, only rows for this company_id (inventory_products.company_id)}';

    protected $description = 'Set all products qty_on_hand to 0; delete inventory moves, cost layers, and purchase history (products kept)';

    public function handle(): int
    {
        if (! $this->option('force')) {
            if ($this->input->isInteractive()) {
                if (! $this->confirm(
                    'This will DELETE purchase_orders(+lines), inventory_moves, inventory_cost_layers, zero warehouse/department stock, and set qty_on_hand = 0. Products stay. Continue?'
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
                Schema::connection('tenant')->withoutForeignKeyConstraints(function () use ($conn, $companyId) {
                    $scopeCompany = function ($q, string $table) use ($companyId) {
                        if ($companyId !== null && Schema::connection('tenant')->hasColumn($table, 'company_id')) {
                            $q->where('company_id', $companyId);
                        }
                    };

                    if (Schema::connection('tenant')->hasTable('credit_ledger')
                        && Schema::connection('tenant')->hasColumn('credit_ledger', 'purchase_order_id')) {
                        $q = $conn->table('credit_ledger')->whereNotNull('purchase_order_id');
                        $scopeCompany($q, 'credit_ledger');
                        $n = $q->delete();
                        $this->line("Removed {$n} purchase-linked credit ledger row(s).");
                    }

                    if (Schema::connection('tenant')->hasTable('journal_entries')
                        && Schema::connection('tenant')->hasTable('journal_entry_lines')) {
                        $ids = $conn->table('journal_entries')
                            ->whereIn('source', ['purchase', 'purchase_order', 'stock_in'])
                            ->pluck('id')
                            ->all();
                        if ($ids !== []) {
                            $nl = $conn->table('journal_entry_lines')->whereIn('journal_entry_id', $ids)->delete();
                            $ne = $conn->table('journal_entries')->whereIn('id', $ids)->delete();
                            $this->line("Removed {$ne} purchase journal entr(y/ies), {$nl} line(s).");
                        }
                    }

                    if (Schema::connection('tenant')->hasTable('inventory_moves')) {
                        $q = $conn->table('inventory_moves');
                        $scopeCompany($q, 'inventory_moves');
                        $nm = $q->delete();
                        $this->line("Removed {$nm} inventory move row(s).");
                    }

                    if (Schema::connection('tenant')->hasTable('purchase_order_lines')) {
                        $q = $conn->table('purchase_order_lines');
                        $scopeCompany($q, 'purchase_order_lines');
                        $npl = $q->delete();
                        $this->line("Removed {$npl} purchase order line(s).");
                    }

                    if (Schema::connection('tenant')->hasTable('purchase_orders')) {
                        $q = $conn->table('purchase_orders');
                        $scopeCompany($q, 'purchase_orders');
                        $npo = $q->delete();
                        $this->line("Removed {$npo} purchase order row(s).");
                    }

                    if (Schema::connection('tenant')->hasTable('inventory_cost_layers')) {
                        $q = $conn->table('inventory_cost_layers');
                        $scopeCompany($q, 'inventory_cost_layers');
                        $nl = $q->delete();
                        $this->line("Removed {$nl} cost layer row(s).");
                    }

                    if (Schema::connection('tenant')->hasTable('inventory_product_stocks')) {
                        $q = $conn->table('inventory_product_stocks');
                        $scopeCompany($q, 'inventory_product_stocks');
                        $ns = $q->update(['qty_on_hand' => 0]);
                        $this->line("Zeroed warehouse/department stock on {$ns} row(s).");
                    }

                    $q = $conn->table('inventory_products');
                    $scopeCompany($q, 'inventory_products');
                    $np = $q->update(['qty_on_hand' => 0]);
                    $this->info("Set qty_on_hand = 0 on {$np} product row(s).");

                    if (Schema::connection('tenant')->hasTable('sync_queue')) {
                        $nsq = $conn->table('sync_queue')
                            ->whereIn('table_name', [
                                'purchase_orders',
                                'purchase_order_lines',
                                'inventory_moves',
                                'inventory_cost_layers',
                                'inventory_product_stocks',
                                'inventory_products',
                            ])
                            ->delete();
                        $this->line("Removed {$nsq} sync queue row(s).");
                    }
                });
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Purchase history deleted. Stock in hand is 0. Products/ingredients kept. Vendors kept.');

        return self::SUCCESS;
    }
}
