<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $onTenant = Schema::getConnection()->getName() === 'tenant';

        Schema::table('employees', function (Blueprint $table) use ($onTenant) {
            if (! Schema::hasColumn('employees', 'user_id')) {
                if ($onTenant) {
                    $table->unsignedBigInteger('user_id')->nullable()->after('id');
                } else {
                    $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
                }
            }
            if (! Schema::hasColumn('employees', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('phone')->constrained('employee_departments')->nullOnDelete();
            }
            if (! Schema::hasColumn('employees', 'designation_id')) {
                $table->foreignId('designation_id')->nullable()->after('department_id')->constrained('employee_designations')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        $onTenant = Schema::getConnection()->getName() === 'tenant';

        Schema::table('employees', function (Blueprint $table) use ($onTenant) {
            if (Schema::hasColumn('employees', 'designation_id')) {
                $table->dropConstrainedForeignId('designation_id');
            }
            if (Schema::hasColumn('employees', 'department_id')) {
                $table->dropConstrainedForeignId('department_id');
            }
            if (Schema::hasColumn('employees', 'user_id')) {
                if ($onTenant) {
                    $table->dropColumn('user_id');
                } else {
                    $table->dropConstrainedForeignId('user_id');
                }
            }
        });
    }
};
