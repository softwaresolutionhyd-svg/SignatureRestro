<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('pos_orders')) {
            return;
        }
        if (Schema::connection('tenant')->hasColumn('pos_orders', 'kitchen_notes')) {
            return;
        }

        Schema::connection('tenant')->table('pos_orders', function (Blueprint $table) {
            $table->text('kitchen_notes')->nullable()->after('order_notes');
        });
    }

    public function down(): void
    {
        if (! Schema::connection('tenant')->hasTable('pos_orders')) {
            return;
        }
        if (! Schema::connection('tenant')->hasColumn('pos_orders', 'kitchen_notes')) {
            return;
        }

        Schema::connection('tenant')->table('pos_orders', function (Blueprint $table) {
            $table->dropColumn('kitchen_notes');
        });
    }
};
