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
        if (Schema::hasTable('inventory_cost_layers')) {
            return;
        }

        Schema::create('inventory_cost_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->decimal('qty_remaining', 14, 3);
            $table->decimal('unit_cost', 14, 6);
            $table->string('source', 30)->nullable(); // purchase|adjust|opening
            $table->string('reference', 80)->nullable(); // PO number etc
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_layers');
    }
};
