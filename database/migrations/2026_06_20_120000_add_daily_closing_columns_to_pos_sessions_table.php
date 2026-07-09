<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_sessions')) {
            return;
        }

        Schema::table('pos_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_sessions', 'business_date')) {
                $table->date('business_date')->nullable()->after('session_no');
            }
            if (! Schema::hasColumn('pos_sessions', 'closing_bank')) {
                $table->decimal('closing_bank', 14, 2)->nullable()->after('closing_cash');
            }
            if (! Schema::hasColumn('pos_sessions', 'closing_card')) {
                $table->decimal('closing_card', 14, 2)->nullable()->after('closing_bank');
            }
            if (! Schema::hasColumn('pos_sessions', 'amount_to_collect')) {
                $table->decimal('amount_to_collect', 14, 2)->nullable()->after('closing_card');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_sessions')) {
            return;
        }

        Schema::table('pos_sessions', function (Blueprint $table) {
            foreach (['business_date', 'closing_bank', 'closing_card', 'amount_to_collect'] as $col) {
                if (Schema::hasColumn('pos_sessions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
