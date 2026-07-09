<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_orders') || Schema::hasColumn('pos_orders', 'bill_discount_percent')) {
            return;
        }

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->decimal('bill_discount_percent', 8, 3)->nullable()->after('bill_tax_percent');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_orders') || ! Schema::hasColumn('pos_orders', 'bill_discount_percent')) {
            return;
        }

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->dropColumn('bill_discount_percent');
        });
    }
};
