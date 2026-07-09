<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $onTenant = Schema::getConnection()->getName() === 'tenant';

        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) use ($onTenant) {
                $table->id();
                if ($onTenant) {
                    $table->unsignedBigInteger('user_id')->nullable();
                } else {
                    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                }
                $table->string('action', 120)->index();
                $table->string('description', 500)->nullable();
                $table->string('subject_type', 120)->nullable()->index();
                $table->unsignedBigInteger('subject_id')->nullable()->index();
                $table->json('properties')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['created_at']);
            });
        }

        if (! Schema::hasTable('employee_attendances')) {
            Schema::create('employee_attendances', function (Blueprint $table) use ($onTenant) {
                $table->id();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                if ($onTenant) {
                    $table->unsignedBigInteger('user_id')->nullable();
                } else {
                    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                }
                $table->date('attendance_date')->index();
                $table->dateTime('clock_in')->nullable();
                $table->dateTime('clock_out')->nullable();
                $table->string('status', 20)->default('present')->index(); // present | absent | leave | half_day
                $table->string('source', 20)->default('self'); // self | manual
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['employee_id', 'attendance_date']);
            });
        }

        if (! Schema::hasTable('payroll_entries')) {
            Schema::create('payroll_entries', function (Blueprint $table) use ($onTenant) {
                $table->id();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->char('period', 7)->index(); // YYYY-MM
                $table->decimal('base_salary', 14, 2)->default(0);
                $table->decimal('bonus', 14, 2)->default(0);
                $table->decimal('deduction', 14, 2)->default(0);
                $table->decimal('net_pay', 14, 2)->default(0);
                $table->string('status', 20)->default('draft')->index();
                $table->timestamp('paid_at')->nullable();
                $table->text('notes')->nullable();
                if ($onTenant) {
                    $table->unsignedBigInteger('created_by')->nullable();
                } else {
                    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                }
                $table->timestamps();

                $table->unique(['employee_id', 'period']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
        Schema::dropIfExists('employee_attendances');
        Schema::dropIfExists('activity_logs');
    }
};
