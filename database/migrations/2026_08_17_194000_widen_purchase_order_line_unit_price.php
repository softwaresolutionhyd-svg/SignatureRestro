<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getName() === 'tenant') {
            return;
        }

        $this->widen();
    }

    public function down(): void
    {
        if (Schema::getConnection()->getName() === 'tenant') {
            return;
        }

        if (! Schema::hasTable('purchase_order_lines')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE purchase_order_lines MODIFY unit_price DECIMAL(14,2) NOT NULL DEFAULT 0');
        } catch (\Throwable) {
        }
    }

    private function widen(): void
    {
        if (! Schema::hasTable('purchase_order_lines')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE purchase_order_lines MODIFY unit_price DECIMAL(14,6) NOT NULL DEFAULT 0');
        } catch (\Throwable) {
        }
    }
};
