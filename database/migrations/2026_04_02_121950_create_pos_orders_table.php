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
        if (Schema::hasTable('pos_orders')) {
            return;
        }

        $onTenant = Schema::getConnection()->getName() === 'tenant';

        Schema::create('pos_orders', function (Blueprint $table) use ($onTenant) {
            $table->id();
            $table->string('order_no', 40)->unique();
            $table->foreignId('session_id')->constrained('pos_sessions')->cascadeOnDelete();
            if ($onTenant) {
                $table->unsignedBigInteger('user_id')->nullable();
            } else {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            }
            $table->foreignId('refund_of_order_id')->nullable()->constrained('pos_orders')->nullOnDelete();
            $table->string('type', 20)->default('sale'); // sale|refund
            $table->string('status', 20)->default('draft')->index(); // draft|paid|cancelled
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_orders');
    }
};
