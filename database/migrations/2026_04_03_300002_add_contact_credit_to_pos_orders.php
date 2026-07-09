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

        if (! Schema::hasColumn('pos_orders', 'contact_id')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->foreignId('contact_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('contacts')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('pos_orders', 'is_credit')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $after = Schema::hasColumn('pos_orders', 'contact_id') ? 'contact_id' : 'user_id';
                $table->boolean('is_credit')->default(false)->after($after);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_orders')) {
            return;
        }

        if (Schema::hasColumn('pos_orders', 'contact_id')) {
            Schema::table('pos_orders', function (Blueprint $table) {
                $table->dropForeign(['contact_id']);
            });
        }

        $toDrop = array_values(array_filter(
            ['contact_id', 'is_credit'],
            fn (string $col) => Schema::hasColumn('pos_orders', $col)
        ));

        if ($toDrop !== []) {
            Schema::table('pos_orders', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }
    }
};
