<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('manufacturing_bom_lines')) {
            return;
        }

        Schema::create('manufacturing_bom_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained('manufacturing_boms')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->decimal('qty', 14, 3);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['bom_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturing_bom_lines');
    }
};
