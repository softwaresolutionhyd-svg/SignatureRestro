<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent: adds packet-size columns if missing (e.g. earlier migration not run or DB copied without them).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_products')) {
            return;
        }

        if (! Schema::hasColumn('inventory_products', 'package_contents_qty')) {
            Schema::table('inventory_products', function (Blueprint $table) {
                $table->decimal('package_contents_qty', 14, 6)->nullable();
            });
        }

        if (! Schema::hasColumn('inventory_products', 'package_contents_uom')) {
            Schema::table('inventory_products', function (Blueprint $table) {
                $table->string('package_contents_uom', 30)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_products')) {
            return;
        }

        $cols = array_values(array_filter([
            Schema::hasColumn('inventory_products', 'package_contents_qty') ? 'package_contents_qty' : null,
            Schema::hasColumn('inventory_products', 'package_contents_uom') ? 'package_contents_uom' : null,
        ]));

        if ($cols !== []) {
            Schema::table('inventory_products', function (Blueprint $table) use ($cols) {
                $table->dropColumn($cols);
            });
        }
    }
};
