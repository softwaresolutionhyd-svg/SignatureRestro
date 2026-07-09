<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or get Administration department
        $dept = EmployeeDepartment::firstOrCreate(
            ['name' => 'Administration'],
            ['active' => true]
        );

        // Create or get Super Administrator designation
        $designation = EmployeeDesignation::firstOrCreate(
            ['name' => 'Super Administrator'],
            ['active' => true]
        );

        // Create admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin12345'),
                'role' => 'admin',
                'email_verified_at' => now(),
                'permissions' => null,
            ]
        );

        // Create employee record
        Employee::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'employee_no' => 'EMP-ADMIN-001',
                'name' => 'Super Admin',
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

        $this->command->info('✅ Admin user created successfully!');
        $this->command->info('📧 Email: admin@example.com');
        $this->command->info('🔑 Password: admin12345');
    }
}
