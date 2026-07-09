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
        if (Schema::hasTable('inventory_moves')) {
            return;
        }

        $onTenant = Schema::getConnection()->getName() === 'tenant';

        Schema::create('inventory_moves', function (Blueprint $table) use ($onTenant) {
            $table->id();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            if ($onTenant) {
                $table->unsignedBigInteger('user_id')->nullable();
            } else {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            }
            $table->string('type', 30); // in | out | adjust
            $table->decimal('qty', 14, 3);
            $table->decimal('qty_before', 14, 3);
            $table->decimal('qty_after', 14, 3);
            $table->string('reference', 80)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_moves');
    }
};
