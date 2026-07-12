<?php

namespace App\Support;

use App\Models\EmployeeStaffCategory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait EnsuresEmployeeStaffCategorySchema
{
    protected function ensureEmployeeStaffCategorySchema(): void
    {
        $schema = Schema::connection('tenant');

        if (! $schema->hasTable('employee_staff_categories')) {
            $schema->create('employee_staff_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('name', 100);
                $table->string('slug', 50);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['company_id', 'slug']);
            });
        }

        if ($schema->hasTable('employees') && ! $schema->hasColumn('employees', 'staff_category_id')) {
            $schema->table('employees', function (Blueprint $table) {
                $table->unsignedBigInteger('staff_category_id')->nullable()->after('designation_id');
                $table->index('staff_category_id');
            });
        }
    }

    protected function seedDefaultStaffCategories(?int $companyId = null): void
    {
        $this->ensureEmployeeStaffCategorySchema();
        $companyId ??= current_company_id();

        $defaults = [
            ['name' => 'FRONT STAFF', 'slug' => 'front-staff', 'sort_order' => 1],
            ['name' => 'KITCHEN STAFF', 'slug' => 'kitchen-staff', 'sort_order' => 2],
            ['name' => 'HOUSE KEEPING', 'slug' => 'house-keeping', 'sort_order' => 3],
            ['name' => 'OTHERS', 'slug' => 'others', 'sort_order' => 4],
        ];

        foreach ($defaults as $row) {
            EmployeeStaffCategory::query()->firstOrCreate(
                ['company_id' => $companyId, 'slug' => $row['slug']],
                ['name' => $row['name'], 'sort_order' => $row['sort_order']]
            );
        }
    }
}
