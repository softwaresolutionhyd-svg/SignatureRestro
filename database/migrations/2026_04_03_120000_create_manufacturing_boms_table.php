<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('manufacturing_boms')) {
            return;
        }

        Schema::create('manufacturing_boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finished_product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->string('name', 120)->default('Default');
            $table->decimal('batch_qty', 14, 3)->default(1);
            $table->boolean('active')->default(true)->index();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['finished_product_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturing_boms');
    }
};
