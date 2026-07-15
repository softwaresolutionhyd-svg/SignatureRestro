<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('pos_order_items', function (Blueprint $table) {
            if (! Schema::connection('tenant')->hasColumn('pos_order_items', 'kitchen_printed_at')) {
                $table->timestamp('kitchen_printed_at')->nullable()->after('kitchen_served_at');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('pos_order_items', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('pos_order_items', 'kitchen_printed_at')) {
                $table->dropColumn('kitchen_printed_at');
            }
        });
    }
};
