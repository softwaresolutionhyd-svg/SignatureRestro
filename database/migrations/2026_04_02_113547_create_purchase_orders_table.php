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
        if (Schema::hasTable('purchase_orders')) {
            return;
        }

        $onTenant = Schema::getConnection()->getName() === 'tenant';

        Schema::create('purchase_orders', function (Blueprint $table) use ($onTenant) {
            $table->id();
            $table->string('number', 40)->unique(); // PO00001
            $table->foreignId('vendor_id')->constrained('purchase_vendors')->restrictOnDelete();
            if ($onTenant) {
                $table->unsignedBigInteger('created_by')->nullable();
            } else {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            }
            $table->string('status', 20)->default('rfq')->index(); // rfq|confirmed|received|cancelled
            $table->date('order_date')->nullable();
            $table->date('expected_date')->nullable();

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
