<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('credit_ledger')) {
            return;
        }

        if ($schema->hasColumn('credit_ledger', 'payroll_entry_id')) {
            return;
        }

        $schema->table('credit_ledger', function (Blueprint $table) {
            $table->unsignedBigInteger('payroll_entry_id')->nullable()->after('pos_order_id');
            $table->index('payroll_entry_id');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('credit_ledger') || ! $schema->hasColumn('credit_ledger', 'payroll_entry_id')) {
            return;
        }

        $schema->table('credit_ledger', function (Blueprint $table) {
            $table->dropIndex(['payroll_entry_id']);
            $table->dropColumn('payroll_entry_id');
        });
    }
};
