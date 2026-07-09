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
        if (! Schema::hasTable('inventory_products')) {
            return;
        }

        Schema::table('inventory_products', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_products', 'gas_charges')) {
                $table->decimal('gas_charges', 12, 2)->default(0)->after('cost');
            }
            if (! Schema::hasColumn('inventory_products', 'profit')) {
                $table->decimal('profit', 12, 2)->default(0)->after('gas_charges');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('inventory_products')) {
            return;
        }

        Schema::table('inventory_products', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_products', 'profit')) {
                $table->dropColumn('profit');
            }
            if (Schema::hasColumn('inventory_products', 'gas_charges')) {
                $table->dropColumn('gas_charges');
            }
        });
    }
};
