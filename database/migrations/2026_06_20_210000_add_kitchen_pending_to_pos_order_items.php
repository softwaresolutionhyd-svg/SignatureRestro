<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pos_order_items', 'kitchen_pending')) {
            Schema::table('pos_order_items', function (Blueprint $table) {
                $table->boolean('kitchen_pending')->default(true)->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pos_order_items', 'kitchen_pending')) {
            Schema::table('pos_order_items', function (Blueprint $table) {
                $table->dropColumn('kitchen_pending');
            });
        }
    }
};
