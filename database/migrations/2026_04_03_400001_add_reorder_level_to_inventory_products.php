<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_products') || Schema::hasColumn('inventory_products', 'reorder_level')) {
            return;
        }

        Schema::table('inventory_products', function (Blueprint $table) {
            $table->decimal('reorder_level', 14, 3)->default(0)->after('qty_on_hand')
                ->comment('Alert when qty_on_hand falls at or below this value. 0 = no alert.');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_products') || ! Schema::hasColumn('inventory_products', 'reorder_level')) {
            return;
        }

        Schema::table('inventory_products', function (Blueprint $table) {
            $table->dropColumn('reorder_level');
        });
    }
};
