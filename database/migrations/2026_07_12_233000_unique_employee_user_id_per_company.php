<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('employees')) {
            return;
        }

        $dupes = DB::connection('tenant')
            ->table('employees')
            ->select('company_id', 'user_id', DB::raw('COUNT(*) as c'))
            ->whereNotNull('user_id')
            ->groupBy('company_id', 'user_id')
            ->having('c', '>', 1)
            ->exists();

        if ($dupes) {
            return;
        }

        $schema = Schema::connection('tenant');
        if ($schema->hasColumn('employees', 'user_id')) {
            try {
                $schema->table('employees', function (Blueprint $table) {
                    $table->unique(['company_id', 'user_id'], 'employees_company_user_unique');
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::connection('tenant')->hasTable('employees')) {
            return;
        }

        try {
            Schema::connection('tenant')->table('employees', function (Blueprint $table) {
                $table->dropUnique('employees_company_user_unique');
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }
};
