<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('purchase_vendors') && ! $schema->hasColumn('purchase_vendors', 'contact_id')) {
            $schema->table('purchase_vendors', function (Blueprint $table) {
                $table->unsignedBigInteger('contact_id')->nullable()->after('company_id');
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

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('purchase_vendors') && $schema->hasColumn('purchase_vendors', 'contact_id')) {
            $schema->table('purchase_vendors', function (Blueprint $table) {
                $table->dropIndex(['contact_id']);
                $table->dropColumn('contact_id');
            });
        }

        if ($schema->hasTable('credit_ledger') && $schema->hasColumn('credit_ledger', 'purchase_order_id')) {
            $schema->table('credit_ledger', function (Blueprint $table) {
                $table->dropIndex(['purchase_order_id']);
                $table->dropColumn('purchase_order_id');
            });
        }
    }
};
