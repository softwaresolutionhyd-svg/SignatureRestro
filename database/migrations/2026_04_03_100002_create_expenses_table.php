<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expenses')) {
            return;
        }

        $onTenant = Schema::getConnection()->getName() === 'tenant';

        Schema::create('expenses', function (Blueprint $table) use ($onTenant) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->string('description', 255);
            $table->date('expense_date');
            $table->decimal('qty', 10, 3)->default(1);
            $table->decimal('unit_amount', 14, 2)->default(0);
            $table->decimal('tax_percent', 6, 3)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);   // before tax
            $table->decimal('grand_total', 14, 2)->default(0);    // after tax
            $table->text('notes')->nullable();
            $table->string('receipt_path', 500)->nullable();
            // Odoo-like status flow: draft → submitted → approved → paid | refused
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            if ($onTenant) {
                $table->unsignedBigInteger('approved_by')->nullable();
            } else {
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            }
            $table->timestamp('paid_at')->nullable();
            $table->string('refuse_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index('expense_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
