<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Applied only on per-company databases (no `companies` table here).
 * Mirrors the tenant parts of companies_and_multi_tenancy without FK to `companies`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tenantTables = [
            'employee_departments',
            'employee_designations',
            'employees',
            'inventory_categories',
            'inventory_products',
            'inventory_product_uom_conversions',
            'inventory_moves',
            'inventory_cost_layers',
            'inventory_product_favorites',
            'purchase_vendors',
            'purchase_orders',
            'purchase_order_lines',
            'pos_sessions',
            'pos_orders',
            'pos_order_items',
            'pos_payments',
            'pos_cash_movements',
            'expense_categories',
            'expenses',
            'contacts',
            'credit_ledger',
            'calendar_events',
            'manufacturing_boms',
            'manufacturing_bom_lines',
            'manufacturing_orders',
            'report_templates',
            'activity_logs',
            'employee_attendances',
            'payroll_entries',
        ];

        foreach ($tenantTables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'company_id')) {
                    return;
                }
                $table->unsignedBigInteger('company_id')->default(1)->after('id');
                $table->index('company_id');
            });
        }

        if (Schema::hasTable('settings') && ! Schema::hasColumn('settings', 'company_id')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropUnique(['key']);
            });
            Schema::table('settings', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->default(1)->after('id');
                $table->index('company_id');
                $table->unique(['company_id', 'key']);
            });
        }

        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropUnique(['employee_no']);
                $table->unique(['company_id', 'employee_no']);
            });
        }

        if (Schema::hasTable('inventory_products')) {
            Schema::table('inventory_products', function (Blueprint $table) {
                $table->dropUnique(['sku']);
                $table->unique(['company_id', 'sku']);
            });
        }

        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropUnique(['number']);
                $table->unique(['company_id', 'number']);
            });
        }

        if (Schema::hasTable('pos_orders')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->dropUnique(['order_no']);
                $table->unique(['company_id', 'order_no']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_orders') && Schema::hasColumn('pos_orders', 'company_id')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->dropUnique(['company_id', 'order_no']);
                $table->unique('order_no');
            });
        }
        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'company_id')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropUnique(['company_id', 'number']);
                $table->unique('number');
            });
        }
        if (Schema::hasTable('inventory_products') && Schema::hasColumn('inventory_products', 'company_id')) {
            Schema::table('inventory_products', function (Blueprint $table) {
                $table->dropUnique(['company_id', 'sku']);
                $table->unique('sku');
            });
        }
        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'company_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropUnique(['company_id', 'employee_no']);
                $table->unique('employee_no');
            });
        }
        if (Schema::hasTable('settings') && Schema::hasColumn('settings', 'company_id')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropUnique(['company_id', 'key']);
                $table->dropColumn('company_id');
                $table->unique('key');
            });
        }

        $tenantTables = [
            'payroll_entries', 'employee_attendances', 'activity_logs', 'report_templates', 'manufacturing_orders',
            'manufacturing_bom_lines', 'manufacturing_boms', 'calendar_events', 'credit_ledger', 'contacts',
            'expenses', 'expense_categories', 'pos_cash_movements', 'pos_payments', 'pos_order_items', 'pos_orders',
            'pos_sessions', 'purchase_order_lines', 'purchase_orders', 'purchase_vendors', 'inventory_product_favorites',
            'inventory_cost_layers', 'inventory_moves', 'inventory_product_uom_conversions', 'inventory_products',
            'inventory_categories', 'employees', 'employee_designations', 'employee_departments',
        ];

        foreach ($tenantTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'company_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('company_id');
                });
            }
        }
    }
};
