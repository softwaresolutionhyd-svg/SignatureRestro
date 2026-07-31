<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('stock_demands')) {
            Schema::connection('tenant')->create('stock_demands', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('demand_no', 40)->index();
                $table->foreignId('department_id')->constrained('inventory_departments')->restrictOnDelete();
                $table->date('demand_date')->index();
                $table->string('status', 20)->default('pending')->index();
                $table->string('note', 255)->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();

                $table->unique(['company_id', 'demand_no']);
            });
        }

        if (! Schema::connection('tenant')->hasTable('stock_demand_lines')) {
            Schema::connection('tenant')->create('stock_demand_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->foreignId('demand_id')->constrained('stock_demands')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
                $table->decimal('qty_uom', 18, 3);
                $table->string('uom', 30);
                $table->decimal('qty_base', 18, 3);
                $table->decimal('issued_qty_base', 18, 3)->default(0);
                $table->string('status', 20)->default('pending')->index();
                $table->timestamp('issued_at')->nullable();
                $table->unsignedBigInteger('issued_by')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('stock_demand_lines');
        Schema::connection('tenant')->dropIfExists('stock_demands');
    }
};
