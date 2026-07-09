<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pos_orders', 'kitchen_status')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->string('kitchen_status', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pos_orders', 'kitchen_status')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->dropColumn('kitchen_status');
            });
        }
    }
};
