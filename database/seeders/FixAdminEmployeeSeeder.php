<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FixAdminEmployeeSeeder extends Seeder
{
    /**
     * Ensures default company exists and a company admin + linked employee (admin@example.com).
     */
    public function run(): void
    {
        $company = Company::query()->first();
        if (! $company) {
            $company = Company::create([
                'name' => 'Default Company',
                'slug' => 'default',
                'active' => true,
            ]);
        }

        $dept = EmployeeDepartment::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Administration'],
            ['active' => true]
        );
        $designation = EmployeeDesignation::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Super Administrator'],
            ['active' => true]
        );

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Company Admin',
                'password' => Hash::make('admin12345'),
                'role' => 'company_admin',
                'company_id' => $company->id,
                'email_verified_at' => now(),
                'permissions' => null,
            ]
        );

        Employee::query()->updateOrCreate(
            ['user_id' => $admin->id],
            [
                'company_id' => $company->id,
                'employee_no' => 'EMP-ADMIN-001',
                'name' => 'Company Admin',
                'email' => 'admin@example.com',
                'phone' => null,
                'department_id' => $dept->id,
                'designation_id' => $designation->id,
                'join_date' => now()->toDateString(),
                'salary' => 0,
                'address' => null,
                'active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'superadmin'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin12345'),
                'role' => 'super_admin',
                'company_id' => null,
                'email_verified_at' => now(),
                'permissions' => null,
            ]
        );

        $legacySuperAdmin = User::query()->where('email', 'superadmin@example.com')->first();
        if ($legacySuperAdmin && ! User::query()->where('email', 'superadmin')->exists()) {
            $legacySuperAdmin->forceFill(['email' => 'superadmin'])->save();
        }

        $this->command?->info('Company admin: admin@example.com / admin12345 (company: '.$company->name.')');
        $this->command?->info('Super admin: superadmin / admin12345');
    }
}
