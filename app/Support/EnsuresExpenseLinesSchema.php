<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait EnsuresExpenseLinesSchema
{
    protected function ensureExpenseLinesSchema(?string $connection = null): void
    {
        $connection = $connection ?: 'tenant';
        $schema = Schema::connection($connection);

        if (! $schema->hasTable('expenses') || $schema->hasTable('expense_lines')) {
            return;
        }

        $schema->create('expense_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('expense_id')->index();
            $table->string('description', 255);
            $table->decimal('qty', 10, 3)->default(1);
            $table->decimal('unit_amount', 14, 2)->default(0);
            $table->decimal('tax_percent', 6, 3)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }
}
