<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds indexes on hot columns used in POS / kitchen / inventory / report queries.
 * Every index is guarded (table + columns must exist, and index must not already
 * exist), so it is safe to run repeatedly and on partially-migrated databases.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{0:string,1:array<int,string>,2:string}>
     */
    private array $indexes = [
        // pos_orders — kitchen board, pending bills, closing stats, table board
        ['pos_orders', ['kitchen_completed_at'], 'idx_po_kitchen_completed'],
        ['pos_orders', ['kitchen_status'], 'idx_po_kitchen_status'],
        ['pos_orders', ['status', 'order_source', 'ready_for_pos_at'], 'idx_po_status_source_ready'],
        ['pos_orders', ['session_id', 'status', 'type', 'paid_at'], 'idx_po_sess_status_type_paid'],
        ['pos_orders', ['status', 'table_id', 'created_at'], 'idx_po_status_table_created'],
        ['pos_orders', ['paid_at'], 'idx_po_paid_at'],
        ['pos_orders', ['created_at'], 'idx_po_created_at'],

        // pos_order_items — kitchen pending / served scans
        ['pos_order_items', ['order_id', 'kitchen_served_at'], 'idx_poi_order_served'],
        ['pos_order_items', ['kitchen_served_at'], 'idx_poi_served'],
        ['pos_order_items', ['kitchen_pending'], 'idx_poi_pending'],

        // pos_sessions — closing / reports
        ['pos_sessions', ['status', 'business_date'], 'idx_ps_status_bdate'],

        // inventory_products — POS catalog & purchase lists
        ['inventory_products', ['active', 'for_pos'], 'idx_ip_active_forpos'],
        ['inventory_products', ['active', 'for_purchase'], 'idx_ip_active_forpurchase'],

        // inventory_product_stocks — warehouse/department stock filters
        ['inventory_product_stocks', ['department_id', 'qty_on_hand'], 'idx_ips_dept_qty'],

        // inventory_moves — issue-stock report
        ['inventory_moves', ['type', 'to_department_id', 'created_at'], 'idx_im_type_todept_created'],

        // credit_ledger — credit reports
        ['credit_ledger', ['contact_id', 'type'], 'idx_cl_contact_type'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $columns, $name]) {
            $this->addIndexSafely($table, $columns, $name);
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [$table, $columns, $name]) {
            if ($this->indexExists($table, $name)) {
                try {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function addIndexSafely(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        if (! Schema::hasColumns($table, $columns)) {
            return;
        }
        if ($this->indexExists($table, $name)) {
            return;
        }

        try {
            Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
        } catch (\Throwable $e) {
            // Index may already exist under another name, or engine limitation — skip.
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        try {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::connection()->getDatabaseName())
                ->where('table_name', $table)
                ->where('index_name', $name)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
};
