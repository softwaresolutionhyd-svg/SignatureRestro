<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait EnsuresEmployeeProfileSchema
{
    private static bool $employeeProfileSchemaReady = false;

    protected function ensureEmployeeProfileSchema(?string $connection = null): void
    {
        if (self::$employeeProfileSchemaReady) {
            return;
        }

        $schema = Schema::connection($connection ?? 'tenant');
        if (! $schema->hasTable('employees')) {
            return;
        }

        $columns = [
            'father_name' => 150,
            'cnic' => 30,
            'city' => 100,
            'district' => 100,
        ];

        foreach ($columns as $column => $length) {
            if ($schema->hasColumn('employees', $column)) {
                continue;
            }
            $schema->table('employees', function (Blueprint $table) use ($column, $length) {
                $table->string($column, $length)->nullable();
            });
        }

        self::$employeeProfileSchemaReady = true;
    }
}
