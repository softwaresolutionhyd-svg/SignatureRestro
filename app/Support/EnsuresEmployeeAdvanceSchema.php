<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait EnsuresEmployeeAdvanceSchema
{
    protected function ensureEmployeeAdvanceSchema(?string $connection = null): void
    {
        $schema = Schema::connection($connection ?? 'tenant');

        if (! $schema->hasTable('employees')) {
            return;
        }

        if (! $schema->hasTable('employee_advances')) {
            $schema->create('employee_advances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->decimal('amount', 14, 2);
                $table->decimal('balance', 14, 2)->default(0);
                $table->date('start_date')->nullable();
                $table->string('status', 20)->default('active');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->foreignId('settled_payroll_entry_id')->nullable()->constrained('payroll_entries')->nullOnDelete();
                $table->string('settled_period', 7)->nullable();
                $table->timestamp('settled_at')->nullable();
                $table->timestamps();
                $table->index(['employee_id', 'status']);
            });
        }
    }
}
