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

        if (! Schema::hasColumn('pos_orders', 'order_source')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->string('order_source', 20)->default('pos')->after('status');
            });
        }

        if (! Schema::hasColumn('pos_orders', 'ready_for_pos_at')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->timestamp('ready_for_pos_at')->nullable()->after('paid_at');
            });
        }

        Schema::withoutForeignKeyConstraints(function () {
            if (Schema::hasColumn('pos_orders', 'session_id')) {
                try {
                    Schema::table('pos_orders', function (Blueprint $table) {
                        $table->unsignedBigInteger('session_id')->nullable()->change();
                    });
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_orders')) {
            return;
        }

        Schema::table('pos_orders', function (Blueprint $table) {
            if (Schema::hasColumn('pos_orders', 'order_source')) {
                $table->dropColumn('order_source');
            }
            if (Schema::hasColumn('pos_orders', 'ready_for_pos_at')) {
                $table->dropColumn('ready_for_pos_at');
            }
        });
    }
};
