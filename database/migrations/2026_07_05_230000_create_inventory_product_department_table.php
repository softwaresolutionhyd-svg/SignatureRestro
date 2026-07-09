<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_product_department')) {
            Schema::create('inventory_product_department', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
                $table->foreignId('department_id')->constrained('inventory_departments')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['company_id', 'product_id', 'department_id'], 'inv_prod_dept_unique');
                $table->index(['department_id', 'product_id']);
            });
        }

        if (! Schema::hasTable('inventory_products') || ! Schema::hasColumn('inventory_products', 'department_id')) {
            return;
        }

        $rows = DB::table('inventory_products')
            ->whereNotNull('department_id')
            ->get(['id', 'company_id', 'department_id']);

        foreach ($rows as $row) {
            DB::table('inventory_product_department')->insertOrIgnore([
                'company_id' => $row->company_id,
                'product_id' => $row->id,
                'department_id' => $row->department_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_product_department');
    }
};
