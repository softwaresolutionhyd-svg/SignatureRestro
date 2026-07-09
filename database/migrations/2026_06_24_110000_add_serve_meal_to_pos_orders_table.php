<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pos_orders', 'serve_meal')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->string('serve_meal', 20)->nullable()->after('serve_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pos_orders', 'serve_meal')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->dropColumn('serve_meal');
            });
        }
    }
};
