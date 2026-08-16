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

        if (! Schema::hasTable('employee_staff_sub_categories')) {
            Schema::create('employee_staff_sub_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('staff_category_id')->index();
                $table->string('name', 100);
                $table->string('slug', 80);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['staff_category_id', 'slug'], 'emp_staff_sub_cat_slug_unique');
            });
        }

        if (Schema::hasTable('employees') && ! Schema::hasColumn('employees', 'staff_sub_category_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->unsignedBigInteger('staff_sub_category_id')->nullable()->after('staff_category_id');
                $table->index('staff_sub_category_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getName() === 'tenant') {
            return;
        }

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'staff_sub_category_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('staff_sub_category_id');
            });
        }

        Schema::dropIfExists('employee_staff_sub_categories');
    }
};
