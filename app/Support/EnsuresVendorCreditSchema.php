<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait EnsuresVendorCreditSchema
{
    protected function ensureVendorCreditSchema(?string $connection = null): void
    {
        $schema = Schema::connection($connection ?? 'tenant');

        if ($schema->hasTable('purchase_vendors') && ! $schema->hasColumn('purchase_vendors', 'contact_id')) {
            $schema->table('purchase_vendors', function (Blueprint $table) {
                $table->unsignedBigInteger('contact_id')->nullable()->after('id');
                $table->index('contact_id');
            });
        }

        if ($schema->hasTable('credit_ledger') && ! $schema->hasColumn('credit_ledger', 'purchase_order_id')) {
            $schema->table('credit_ledger', function (Blueprint $table) {
                $table->unsignedBigInteger('purchase_order_id')->nullable()->after('pos_order_id');
                $table->index('purchase_order_id');
            });
        }
    }
}
