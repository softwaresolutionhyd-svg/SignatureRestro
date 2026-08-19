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

        if (! Schema::hasTable('employee_advances')) {
            Schema::create('employee_advances', function (Blueprint $table) {
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

        if (Schema::hasTable('payroll_entries') && ! Schema::hasColumn('payroll_entries', 'advance')) {
            Schema::table('payroll_entries', function (Blueprint $table) {
                $table->decimal('advance', 14, 2)->default(0)->after('loan');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_entries') && Schema::hasColumn('payroll_entries', 'advance')) {
            Schema::table('payroll_entries', function (Blueprint $table) {
                $table->dropColumn('advance');
            });
        }

        Schema::dropIfExists('employee_advances');
    }
};
