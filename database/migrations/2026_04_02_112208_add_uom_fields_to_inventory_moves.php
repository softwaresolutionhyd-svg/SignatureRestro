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
        Schema::table('inventory_moves', function (Blueprint $table) {
            $table->string('uom', 30)->nullable()->after('type');
            $table->decimal('qty_uom', 14, 3)->nullable()->after('qty');
            $table->decimal('factor_to_base', 18, 6)->nullable()->after('qty_uom');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_moves', function (Blueprint $table) {
            $table->dropColumn(['uom', 'qty_uom', 'factor_to_base']);
        });
    }
};
