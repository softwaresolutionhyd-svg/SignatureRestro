<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_departments', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_departments', 'is_warehouse')) {
                $table->boolean('is_warehouse')->default(false)->after('active');
            }
        });

        if (! Schema::hasTable('inventory_product_stocks')) {
            Schema::create('inventory_product_stocks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
                $table->foreignId('department_id')->constrained('inventory_departments')->cascadeOnDelete();
                $table->decimal('qty_on_hand', 14, 3)->default(0);
                $table->timestamps();

                $table->unique(['company_id', 'product_id', 'department_id'], 'inv_prod_stocks_co_prod_dept_uniq');
                $table->index(['department_id', 'product_id']);
            });
        }

        Schema::table('inventory_moves', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_moves', 'from_department_id')) {
                $table->foreignId('from_department_id')->nullable()->after('product_id')->constrained('inventory_departments')->nullOnDelete();
            }
            if (! Schema::hasColumn('inventory_moves', 'to_department_id')) {
                $table->foreignId('to_department_id')->nullable()->after('from_department_id')->constrained('inventory_departments')->nullOnDelete();
            }
        });

        $this->seedWarehouseAndBackfill();
    }

    public function down(): void
    {
        Schema::table('inventory_moves', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_moves', 'to_department_id')) {
                $table->dropConstrainedForeignId('to_department_id');
            }
            if (Schema::hasColumn('inventory_moves', 'from_department_id')) {
                $table->dropConstrainedForeignId('from_department_id');
            }
        });

        Schema::dropIfExists('inventory_product_stocks');

        Schema::table('inventory_departments', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_departments', 'is_warehouse')) {
                $table->dropColumn('is_warehouse');
            }
        });
    }

    private function seedWarehouseAndBackfill(): void
    {
        if (! Schema::hasTable('inventory_departments') || ! Schema::hasTable('inventory_products')) {
            return;
        }

        $companyIds = DB::table('inventory_departments')->distinct()->pluck('company_id')
            ->merge(DB::table('inventory_products')->distinct()->pluck('company_id'))
            ->unique()
            ->filter();

        foreach ($companyIds as $companyId) {
            $warehouseId = DB::table('inventory_departments')
                ->where('company_id', $companyId)
                ->where('is_warehouse', true)
                ->value('id');

            if (! $warehouseId) {
                $warehouseId = DB::table('inventory_departments')
                    ->where('company_id', $companyId)
                    ->whereRaw('LOWER(name) = ?', ['warehouse'])
                    ->value('id');
            }

            if ($warehouseId) {
                DB::table('inventory_departments')->where('id', $warehouseId)->update([
                    'is_warehouse' => true,
                    'active' => true,
                    'name' => 'Warehouse',
                    'updated_at' => now(),
                ]);
            } else {
                $warehouseId = DB::table('inventory_departments')->insertGetId([
                    'company_id' => $companyId,
                    'name' => 'Warehouse',
                    'active' => true,
                    'is_warehouse' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $products = DB::table('inventory_products')
                ->where('company_id', $companyId)
                ->get(['id', 'qty_on_hand']);

            foreach ($products as $product) {
                $qty = (float) ($product->qty_on_hand ?? 0);
                $exists = DB::table('inventory_product_stocks')
                    ->where('company_id', $companyId)
                    ->where('product_id', $product->id)
                    ->where('department_id', $warehouseId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('inventory_product_stocks')->insert([
                    'company_id' => $companyId,
                    'product_id' => $product->id,
                    'department_id' => $warehouseId,
                    'qty_on_hand' => $qty,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
