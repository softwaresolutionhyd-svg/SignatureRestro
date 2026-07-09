<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_orders') || Schema::hasColumn('pos_orders', 'service_type')) {
            return;
        }

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->string('service_type', 20)->nullable()->after('customer_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_orders') || ! Schema::hasColumn('pos_orders', 'service_type')) {
            return;
        }

        Schema::table('pos_orders', function (Blueprint $table) {
            $table->dropColumn('service_type');
        });
    }
};
