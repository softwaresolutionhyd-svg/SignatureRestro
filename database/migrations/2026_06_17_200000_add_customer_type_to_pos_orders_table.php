<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_orders') || Schema::hasColumn('pos_orders', 'customer_type')) {
            return;
        }

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->string('customer_type', 20)->default('mess_use')->after('contact_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_orders') || ! Schema::hasColumn('pos_orders', 'customer_type')) {
            return;
        }

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->dropColumn('customer_type');
        });
    }
};
