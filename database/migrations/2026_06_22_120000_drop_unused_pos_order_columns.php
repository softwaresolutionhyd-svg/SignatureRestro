<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_orders')) {
            return;
        }

        if (Schema::hasColumn('pos_orders', 'order_notes')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->dropColumn('order_notes');
            });
        }

        if (Schema::hasColumn('pos_orders', 'ast_offr')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->dropColumn('ast_offr');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_orders')) {
            return;
        }

        if (! Schema::hasColumn('pos_orders', 'order_notes')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->text('order_notes')->nullable()->after('waiter_name');
            });
        }
    }
};
