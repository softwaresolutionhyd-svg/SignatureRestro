<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pos_orders', 'kitchen_sort')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->unsignedInteger('kitchen_sort')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pos_orders', 'kitchen_sort')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->dropColumn('kitchen_sort');
            });
        }
    }
};
