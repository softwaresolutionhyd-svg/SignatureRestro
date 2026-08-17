<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');
        if (! $schema->hasTable('purchase_order_lines')) {
            return;
        }

        try {
            DB::connection('tenant')->statement(
                'ALTER TABLE purchase_order_lines MODIFY unit_price DECIMAL(14,6) NOT NULL DEFAULT 0'
            );
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');
        if (! $schema->hasTable('purchase_order_lines')) {
            return;
        }

        try {
            DB::connection('tenant')->statement(
                'ALTER TABLE purchase_order_lines MODIFY unit_price DECIMAL(14,2) NOT NULL DEFAULT 0'
            );
        } catch (\Throwable) {
        }
    }
};
