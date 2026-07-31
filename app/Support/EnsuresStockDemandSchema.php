<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait EnsuresStockDemandSchema
{
    protected function ensureStockDemandSchema(?string $connection = null): void
    {
        $schema = Schema::connection($connection ?? 'tenant');

        if (! $schema->hasTable('stock_demands')) {
            $schema->create('stock_demands', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('demand_no', 40)->index();
                $table->unsignedBigInteger('department_id')->index();
                $table->date('demand_date')->index();
                $table->string('status', 20)->default('pending')->index();
                $table->string('note', 255)->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('stock_demand_lines')) {
            $schema->create('stock_demand_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('demand_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('qty_uom', 18, 3);
                $table->string('uom', 30);
                $table->decimal('qty_base', 18, 3);
                $table->decimal('issued_qty_base', 18, 3)->default(0);
                $table->string('status', 20)->default('pending')->index();
                $table->timestamp('issued_at')->nullable();
                $table->unsignedBigInteger('issued_by')->nullable()->index();
                $table->timestamps();
            });
        }
    }
}
