<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_departments')) {
            return;
        }

        Schema::table('inventory_departments', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_departments', 'printer_ip')) {
                $table->string('printer_ip', 45)->nullable()->after('is_warehouse');
            }
            if (! Schema::hasColumn('inventory_departments', 'printer_port')) {
                $table->unsignedInteger('printer_port')->nullable()->after('printer_ip');
            }
            if (! Schema::hasColumn('inventory_departments', 'printer_name')) {
                $table->string('printer_name', 100)->nullable()->after('printer_port');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_departments')) {
            return;
        }

        Schema::table('inventory_departments', function (Blueprint $table) {
            foreach (['printer_name', 'printer_port', 'printer_ip'] as $col) {
                if (Schema::hasColumn('inventory_departments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
