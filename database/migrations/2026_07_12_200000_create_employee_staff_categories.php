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

        if (! Schema::hasTable('employee_staff_categories')) {
            Schema::create('employee_staff_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('name', 100);
                $table->string('slug', 50);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['company_id', 'slug']);
            });
        }

        if (Schema::hasTable('employees') && ! Schema::hasColumn('employees', 'staff_category_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->foreignId('staff_category_id')
                    ->nullable()
                    ->after('designation_id')
                    ->constrained('employee_staff_categories')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'staff_category_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropConstrainedForeignId('staff_category_id');
            });
        }

        Schema::dropIfExists('employee_staff_categories');
    }
};
