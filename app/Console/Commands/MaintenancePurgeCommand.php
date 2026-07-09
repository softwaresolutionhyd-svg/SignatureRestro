<?php

namespace App\Console\Commands;

use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MaintenancePurgeCommand extends Command
{
    protected $signature = 'maintenance:purge
        {--force : Skip confirmation}
        {--company= : Limit to company_id when column exists}';

    protected $description = 'Delete all maintenance items, demands, issues, locations, and categories';

    public function handle(): int
    {
        if (! $this->option('force')) {
            if ($this->input->isInteractive()) {
                if (! $this->confirm('Delete ALL maintenance items, demands, issues, locations, and categories?')) {
                    return self::FAILURE;
                }
            } else {
                $this->error('Non-interactive: run with --force');

                return self::FAILURE;
            }
        }

        $schema = Schema::connection('tenant');
        if (! $schema->hasTable('inventory_products')) {
            $this->error('Tenant inventory_products table not found.');

            return self::FAILURE;
        }

        $companyId = $this->option('company');
        $companyId = $companyId !== null && $companyId !== '' ? (int) $companyId : null;

        $category = InventoryCategory::query()
            ->whereRaw('LOWER(name) = ?', ['maintenance'])
            ->first();

        if ($category === null) {
            $this->warn('No Maintenance category found — nothing to purge.');

            return self::SUCCESS;
        }

        $productIds = InventoryProduct::query()
            ->where('category_id', $category->id)
            ->pluck('id')
            ->all();

        $conn = DB::connection('tenant');

        try {
            $conn->transaction(function () use ($conn, $schema, $productIds, $companyId) {
                $scope = function ($query, string $table) use ($companyId, $schema) {
                    if ($companyId !== null && $schema->hasColumn($table, 'company_id')) {
                        $query->where('company_id', $companyId);
                    }

                    return $query;
                };

                if ($schema->hasTable('maintenance_demand_lines')) {
                    $q = $conn->table('maintenance_demand_lines');
                    $scope($q, 'maintenance_demand_lines');
                    $n = $q->delete();
                    $this->line("Removed {$n} demand line(s).");
                }

                if ($schema->hasTable('maintenance_demands')) {
                    $q = $conn->table('maintenance_demands');
                    $scope($q, 'maintenance_demands');
                    $n = $q->delete();
                    $this->line("Removed {$n} demand(s).");
                }

                if ($schema->hasTable('maintenance_issues')) {
                    $q = $conn->table('maintenance_issues');
                    $scope($q, 'maintenance_issues');
                    $n = $q->delete();
                    $this->line("Removed {$n} issue(s).");
                }

                if ($schema->hasTable('maintenance_locations')) {
                    $q = $conn->table('maintenance_locations');
                    $scope($q, 'maintenance_locations');
                    $n = $q->delete();
                    $this->line("Removed {$n} location(s).");
                }

                if ($schema->hasTable('maintenance_categories')) {
                    $q = $conn->table('maintenance_categories');
                    $scope($q, 'maintenance_categories');
                    $n = $q->delete();
                    $this->line("Removed {$n} demand category row(s).");
                }

                if ($productIds !== [] && $schema->hasTable('inventory_product_uom_conversions')) {
                    $n = $conn->table('inventory_product_uom_conversions')
                        ->whereIn('product_id', $productIds)
                        ->delete();
                    $this->line("Removed {$n} UOM conversion(s).");
                }

                if ($productIds !== []) {
                    $q = $conn->table('inventory_products')->whereIn('id', $productIds);
                    $scope($q, 'inventory_products');
                    $n = $q->delete();
                    $this->line("Removed {$n} maintenance item(s).");
                }
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Maintenance data purged.');

        return self::SUCCESS;
    }
}
