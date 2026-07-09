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
        if (Schema::hasTable('inventory_product_favorites')) {
            return;
        }

        $onTenant = Schema::getConnection()->getName() === 'tenant';

        Schema::create('inventory_product_favorites', function (Blueprint $table) use ($onTenant) {
            $table->id();
            if ($onTenant) {
                $table->unsignedBigInteger('user_id');
            } else {
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            }
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
            $table->index(['product_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_product_favorites');
    }
};
