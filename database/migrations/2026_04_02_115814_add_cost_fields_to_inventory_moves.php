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
            $table->decimal('unit_cost', 14, 6)->nullable()->after('factor_to_base');
            $table->decimal('total_cost', 14, 6)->nullable()->after('unit_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_moves', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'total_cost']);
        });
    }
};
