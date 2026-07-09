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
        if (Schema::hasTable('inventory_products')) {
            return;
        }

        Schema::create('inventory_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();
            $table->string('sku', 80)->unique();
            $table->string('name', 200);
            $table->string('uom', 30)->default('Units');
            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('qty_on_hand', 14, 3)->default(0);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->index(['category_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_products');
    }
};
