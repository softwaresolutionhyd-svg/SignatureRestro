<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_orders', 'kitchen_pos_x')) {
                $table->unsignedSmallInteger('kitchen_pos_x')->nullable()->after('kitchen_sort');
            }
            if (! Schema::hasColumn('pos_orders', 'kitchen_pos_y')) {
                $table->unsignedSmallInteger('kitchen_pos_y')->nullable()->after('kitchen_pos_x');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_orders', function (Blueprint $table) {
            if (Schema::hasColumn('pos_orders', 'kitchen_pos_y')) {
                $table->dropColumn('kitchen_pos_y');
            }
            if (Schema::hasColumn('pos_orders', 'kitchen_pos_x')) {
                $table->dropColumn('kitchen_pos_x');
            }
        });
    }
};
