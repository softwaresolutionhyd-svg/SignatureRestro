<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');
        if (! $schema->hasTable('purchase_orders')) {
            return;
        }

        $schema->table('purchase_orders', function (Blueprint $table) use ($schema) {
            if (! $schema->hasColumn('purchase_orders', 'discount_mode')) {
                $table->string('discount_mode', 16)->default('percent');
            }
            if (! $schema->hasColumn('purchase_orders', 'discount_value')) {
                $table->decimal('discount_value', 14, 3)->default(0);
            }
            if (! $schema->hasColumn('purchase_orders', 'discount_total')) {
                $table->decimal('discount_total', 14, 2)->default(0);
            }
            if (! $schema->hasColumn('purchase_orders', 'tax_mode')) {
                $table->string('tax_mode', 16)->default('percent');
            }
            if (! $schema->hasColumn('purchase_orders', 'tax_value')) {
                $table->decimal('tax_value', 14, 3)->default(0);
            }
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');
        if (! $schema->hasTable('purchase_orders')) {
            return;
        }

        $schema->table('purchase_orders', function (Blueprint $table) use ($schema) {
            foreach (['discount_mode', 'discount_value', 'discount_total', 'tax_mode', 'tax_value'] as $col) {
                if ($schema->hasColumn('purchase_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
