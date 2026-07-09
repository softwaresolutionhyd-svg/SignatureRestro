<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pos_orders', 'kitchen_preparing_at')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->timestamp('kitchen_preparing_at')->nullable()->after('kitchen_completed_at');
            });
        }
        if (! Schema::hasColumn('pos_orders', 'kitchen_ready_at')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->timestamp('kitchen_ready_at')->nullable()->after('kitchen_preparing_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pos_orders', 'kitchen_ready_at')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->dropColumn('kitchen_ready_at');
            });
        }
        if (Schema::hasColumn('pos_orders', 'kitchen_preparing_at')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->dropColumn('kitchen_preparing_at');
            });
        }
    }
};
