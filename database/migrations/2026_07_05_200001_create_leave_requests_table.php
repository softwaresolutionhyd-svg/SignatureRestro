<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_requests')) {
            return;
        }

        $onTenant = Schema::getConnection()->getName() === 'tenant';

        Schema::create('leave_requests', function (Blueprint $table) use ($onTenant) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            if ($onTenant) {
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
            } else {
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            }
            $table->string('leave_type', 30)->default('annual')->index();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days')->default(1);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
