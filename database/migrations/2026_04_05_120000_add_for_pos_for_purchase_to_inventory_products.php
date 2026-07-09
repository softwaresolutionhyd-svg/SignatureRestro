<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_products', function (Blueprint $table) {
            $table->boolean('for_pos')->default(true)->after('active');
            $table->boolean('for_purchase')->default(true)->after('for_pos');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_products', function (Blueprint $table) {
            $table->dropColumn(['for_pos', 'for_purchase']);
        });
    }
};
