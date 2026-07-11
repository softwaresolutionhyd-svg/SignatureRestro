<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_orders') || Schema::hasColumn('pos_orders', 'is_owner_discount')) {
            return;
        }

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->boolean('is_owner_discount')->default(false)->after('bill_discount_percent');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_orders') || ! Schema::hasColumn('pos_orders', 'is_owner_discount')) {
            return;
        }

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->dropColumn('is_owner_discount');
        });
    }
};
