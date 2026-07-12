<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait EnsuresEmployeeLoanSchema
{
    protected function ensureEmployeeLoanSchema(?string $connection = null): void
    {
        $schema = Schema::connection($connection ?? 'tenant');

        if (! $schema->hasTable('employees')) {
            return;
        }

        if (! $schema->hasTable('employee_loans')) {
            $schema->create('employee_loans', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->decimal('loan_amount', 14, 2);
                $table->decimal('monthly_installment', 14, 2);
                $table->decimal('balance', 14, 2);
                $table->date('start_date')->nullable();
                $table->string('status', 20)->default('active');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['employee_id', 'status']);
            });
        }

        if (! $schema->hasTable('payroll_entries')) {
            return;
        }

        if (! $schema->hasTable('employee_loan_payments')) {
            $schema->create('employee_loan_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->foreignId('employee_loan_id')->constrained('employee_loans')->cascadeOnDelete();
                $table->foreignId('payroll_entry_id')->nullable()->constrained('payroll_entries')->nullOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('period', 7);
                $table->decimal('amount', 14, 2);
                $table->decimal('balance_after', 14, 2);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['employee_loan_id', 'period']);
            });
        }
    }
}
