<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pos_order_items', 'kitchen_served_at')) {
            Schema::table('pos_order_items', function (Blueprint $table) {
                $table->timestamp('kitchen_served_at')->nullable()->after('kitchen_pending');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pos_order_items', 'kitchen_served_at')) {
            Schema::table('pos_order_items', function (Blueprint $table) {
                $table->dropColumn('kitchen_served_at');
            });
        }
    }
};
