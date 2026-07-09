<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_orders') || Schema::hasColumn('pos_orders', 'order_notes')) {
            return;
        }

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->text('order_notes')->nullable()->after('waiter_name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_orders') || ! Schema::hasColumn('pos_orders', 'order_notes')) {
            return;
        }

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->dropColumn('order_notes');
        });
    }
};
