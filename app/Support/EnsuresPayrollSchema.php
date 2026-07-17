<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

trait EnsuresPayrollSchema
{
    protected function ensurePayrollSchema(?string $connection = null): void
    {
        $conn = $connection ?? 'tenant';
        $cacheKey = 'payroll_schema:ensured:v1:'.$conn;

        try {
            if (Cache::get($cacheKey)) {
                return;
            }
        } catch (\Throwable) {
            // run ensure below
        }

        $schema = Schema::connection($conn);

        if (! $schema->hasTable('employees')) {
            return;
        }

        if (! $schema->hasColumn('employees', 'contact_id') && $schema->hasTable('contacts')) {
            $schema->table('employees', function (Blueprint $table) {
                $table->foreignId('contact_id')->nullable()->after('user_id')->constrained('contacts')->nullOnDelete();
            });
        }

        if (! $schema->hasTable('payroll_entries')) {
            return;
        }

        if (! $schema->hasColumn('payroll_entries', 'food_bill')) {
            $schema->table('payroll_entries', function (Blueprint $table) {
                $table->decimal('food_bill', 14, 2)->default(0)->after('deduction');
            });
        }

        if (! $schema->hasColumn('payroll_entries', 'loan')) {
            $schema->table('payroll_entries', function (Blueprint $table) {
                $table->decimal('loan', 14, 2)->default(0)->after('food_bill');
            });
        }

        try {
            Cache::put($cacheKey, true, now()->addHours(12));
        } catch (\Throwable) {
            // ignore
        }
    }
}
