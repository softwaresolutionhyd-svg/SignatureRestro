<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_orders', function (Blueprint $table) {
            $table->decimal('cash_tendered', 12, 2)->nullable()->after('grand_total');
            $table->decimal('cash_change', 12, 2)->nullable()->after('cash_tendered');
        });
    }

    public function down(): void
    {
        Schema::table('pos_orders', function (Blueprint $table) {
            $table->dropColumn(['cash_tendered', 'cash_change']);
        });
    }
};
