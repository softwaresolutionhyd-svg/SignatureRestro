<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('stock_checks')) {
            return;
        }

        Schema::connection('tenant')->create('stock_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('number', 40)->unique();
            $table->string('title')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('stock_check_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->foreignId('stock_check_id')->constrained('stock_checks')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->decimal('expected_qty', 18, 6);
            $table->decimal('counted_qty', 18, 6)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('stock_check_lines');
        Schema::connection('tenant')->dropIfExists('stock_checks');
    }
};
