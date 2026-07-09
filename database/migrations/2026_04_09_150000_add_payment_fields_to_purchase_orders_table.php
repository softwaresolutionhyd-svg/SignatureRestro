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
        if (! Schema::hasTable('purchase_orders')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_orders', 'purchase_type')) {
                $table->string('purchase_type', 20)->default('debit')->after('status'); // debit|credit
            }
            if (! Schema::hasColumn('purchase_orders', 'payment_status')) {
                $table->string('payment_status', 20)->default('paid')->after('purchase_type'); // unpaid|paid
            }
            if (! Schema::hasColumn('purchase_orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('received_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
            if (Schema::hasColumn('purchase_orders', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
            if (Schema::hasColumn('purchase_orders', 'purchase_type')) {
                $table->dropColumn('purchase_type');
            }
        });
    }
};
