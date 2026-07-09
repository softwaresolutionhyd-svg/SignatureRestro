<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('pos_orders')) {
            return;
        }

        Schema::table('pos_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_orders', 'table_id') && Schema::hasTable('pos_tables')) {
                $table->foreignId('table_id')->nullable()->after('session_id')->constrained('pos_tables')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('pos_orders')) {
            return;
        }

        Schema::table('pos_orders', function (Blueprint $table) {
            if (Schema::hasColumn('pos_orders', 'table_id')) {
                $table->dropConstrainedForeignId('table_id');
            }
        });
    }
};
