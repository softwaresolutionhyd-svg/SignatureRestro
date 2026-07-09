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
        if (Schema::hasTable('pos_payments')) {
            return;
        }

        Schema::create('pos_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('pos_orders')->cascadeOnDelete();
            $table->string('method', 30); // cash|card|bank
            $table->decimal('amount', 14, 2);
            $table->string('reference', 100)->nullable();
            $table->timestamps();

            $table->index(['order_id', 'method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_payments');
    }
};
