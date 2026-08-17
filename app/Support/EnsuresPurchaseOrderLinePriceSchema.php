<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait EnsuresPurchaseOrderLinePriceSchema
{
    protected function ensurePurchaseOrderLinePriceSchema(?string $connection = null): void
    {
        $connection = $connection ?: 'tenant';
        $schema = Schema::connection($connection);

        if (! $schema->hasTable('purchase_order_lines') || ! $schema->hasColumn('purchase_order_lines', 'unit_price')) {
            return;
        }

        $scale = 2;
        try {
            $row = DB::connection($connection)->selectOne(
                "SELECT NUMERIC_SCALE AS s FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'purchase_order_lines'
                   AND COLUMN_NAME = 'unit_price'"
            );
            $scale = (int) ($row->s ?? 2);
        } catch (\Throwable) {
        }

        if ($scale >= 6) {
            return;
        }

        try {
            DB::connection($connection)->statement(
                'ALTER TABLE purchase_order_lines MODIFY unit_price DECIMAL(14,6) NOT NULL DEFAULT 0'
            );
        } catch (\Throwable) {
        }
    }
}
