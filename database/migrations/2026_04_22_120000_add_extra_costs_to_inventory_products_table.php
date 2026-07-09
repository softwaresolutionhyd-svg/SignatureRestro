<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_products')) {
            return;
        }

        Schema::table('inventory_products', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_products', 'extra_costs')) {
                $table->json('extra_costs')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_products')) {
            return;
        }

        Schema::table('inventory_products', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_products', 'extra_costs')) {
                $table->dropColumn('extra_costs');
            }
        });
    }
};
