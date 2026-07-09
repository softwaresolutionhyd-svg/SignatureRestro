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
        if (Schema::hasTable('purchase_order_lines')) {
            return;
        }

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();

            $table->string('description', 255)->nullable();
            $table->string('uom', 30);
            $table->decimal('qty', 14, 3);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('tax_percent', 6, 3)->default(0); // e.g. 17

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            $table->timestamps();

            $table->index(['purchase_order_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
