<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('inventory_product_uom_conversions')) {
            return;
        }

        Schema::create('inventory_product_uom_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->string('uom', 30);
            $table->decimal('factor_to_base', 18, 6); // qty_in_this_uom * factor = qty_in_base_uom
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->unique(['product_id', 'uom']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_product_uom_conversions');
    }
};
