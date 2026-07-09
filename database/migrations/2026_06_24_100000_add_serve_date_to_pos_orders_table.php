<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pos_orders', 'serve_date')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->date('serve_date')->nullable()->after('serve_time');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pos_orders', 'serve_date')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->dropColumn('serve_date');
            });
        }
    }
};
