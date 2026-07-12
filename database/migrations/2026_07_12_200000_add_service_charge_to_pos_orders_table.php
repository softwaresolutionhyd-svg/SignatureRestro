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

        Schema::table('pos_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_orders', 'service_charge_percent')) {
                $table->decimal('service_charge_percent', 8, 3)->nullable()->after('tax_total');
            }
            if (! Schema::hasColumn('pos_orders', 'service_charge_total')) {
                $table->decimal('service_charge_total', 12, 2)->default(0)->after('service_charge_percent');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_orders')) {
            return;
        }

        Schema::table('pos_orders', function (Blueprint $table) {
            if (Schema::hasColumn('pos_orders', 'service_charge_total')) {
                $table->dropColumn('service_charge_total');
            }
            if (Schema::hasColumn('pos_orders', 'service_charge_percent')) {
                $table->dropColumn('service_charge_percent');
            }
        });
    }
};
