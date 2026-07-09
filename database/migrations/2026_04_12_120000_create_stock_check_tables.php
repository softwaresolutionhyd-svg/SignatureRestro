<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_checks')) {
            return;
        }

        Schema::create('stock_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('number', 40);
            $table->string('title')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });

        Schema::create('stock_check_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
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
        Schema::dropIfExists('stock_check_lines');
        Schema::dropIfExists('stock_checks');
    }
};
