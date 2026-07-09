<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pos_orders', 'serve_time')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->string('serve_time', 10)->nullable()->after('waiter_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pos_orders', 'serve_time')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->dropColumn('serve_time');
            });
        }
    }
};
