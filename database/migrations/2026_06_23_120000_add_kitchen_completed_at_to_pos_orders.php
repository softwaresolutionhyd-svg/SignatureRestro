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

        if (! Schema::hasColumn('pos_orders', 'kitchen_completed_at')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->timestamp('kitchen_completed_at')->nullable()->after('ready_for_pos_at');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_orders')) {
            return;
        }

        if (Schema::hasColumn('pos_orders', 'kitchen_completed_at')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->dropColumn('kitchen_completed_at');
            });
        }
    }
};
