<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getName() === 'tenant') {
            return;
        }

        $this->addColumns();
    }

    public function down(): void
    {
        if (Schema::getConnection()->getName() === 'tenant') {
            return;
        }

        $this->dropColumns();
    }

    private function addColumns(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_orders', 'discount_mode')) {
                $table->string('discount_mode', 16)->default('percent');
            }
            if (! Schema::hasColumn('purchase_orders', 'discount_value')) {
                $table->decimal('discount_value', 14, 3)->default(0);
            }
            if (! Schema::hasColumn('purchase_orders', 'discount_total')) {
                $table->decimal('discount_total', 14, 2)->default(0);
            }
            if (! Schema::hasColumn('purchase_orders', 'tax_mode')) {
                $table->string('tax_mode', 16)->default('percent');
            }
            if (! Schema::hasColumn('purchase_orders', 'tax_value')) {
                $table->decimal('tax_value', 14, 3)->default(0);
            }
        });
    }

    private function dropColumns(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            return;
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            foreach (['discount_mode', 'discount_value', 'discount_total', 'tax_mode', 'tax_value'] as $col) {
                if (Schema::hasColumn('purchase_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
