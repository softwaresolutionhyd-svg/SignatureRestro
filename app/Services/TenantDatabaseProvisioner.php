<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class TenantDatabaseProvisioner
{
    /** Migrations that must run only on the landlord (central) database. */
    private const LANDLORD_MIGRATION_FILES = [
        '2014_10_12_000000_create_users_table.php',
        '2014_10_12_100000_create_password_resets_table.php',
        '2014_10_12_100000_create_password_reset_tokens_table.php',
        '2019_08_19_000000_create_failed_jobs_table.php',
        '2019_12_14_000001_create_personal_access_tokens_table.php',
        '2026_04_02_104147_add_role_to_users_table.php',
        '2026_04_02_120408_create_notifications_table.php',
        '2026_04_02_150539_add_permissions_to_users_table.php',
        '2026_04_06_200000_companies_and_multi_tenancy.php',
        '2026_04_07_140000_add_database_name_to_companies_table.php',
        '2026_04_08_120000_add_tenant_provision_status_to_companies_table.php',
        '2026_04_10_000001_create_company_updates_table.php',
        '2026_04_11_100000_add_feature_key_to_company_updates_table.php',
        '2026_04_11_100001_create_company_installed_features_table.php',
    ];

    private const RESERVED_DATABASE_NAMES = [
        'information_schema', 'mysql', 'performance_schema', 'sys',
    ];

    public static function normalizeDatabaseName(string $name): string
    {
        return strtolower(trim($name));
    }

    public static function validateDatabaseName(string $name): ?string
    {
        $n = self::normalizeDatabaseName($name);
        if ($n === '') {
            return 'Database name is required.';
        }
        if (strlen($n) > 64) {
            return 'Database name must be at most 64 characters.';
        }
        if (! preg_match('/^[a-z0-9_]+$/', $n)) {
            return 'Use only lowercase letters, numbers, and underscores.';
        }
        if (in_array($n, self::RESERVED_DATABASE_NAMES, true)) {
            return 'This database name is reserved by MySQL.';
        }
        $master = strtolower((string) config('database.connections.mysql.database', ''));
        if ($master !== '' && $n === $master) {
            return 'Choose a different name than the main application database.';
        }

        return null;
    }

    public static function databaseExists(string $databaseName): bool
    {
        $n = self::normalizeDatabaseName($databaseName);
        $row = DB::connection('mysql')->selectOne(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$n]
        );

        return $row !== null;
    }

    public static function createEmptyDatabase(string $databaseName): void
    {
        $n = self::normalizeDatabaseName($databaseName);
        $quoted = '`'.str_replace('`', '``', $n).'`';
        DB::connection('mysql')->statement(
            "CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }

    public static function dropDatabase(string $databaseName): void
    {
        $n = self::normalizeDatabaseName($databaseName);
        $quoted = '`'.str_replace('`', '``', $n).'`';
        DB::connection('mysql')->statement("DROP DATABASE IF EXISTS {$quoted}");
    }

    public function provision(string $databaseName): void
    {
        $n = self::normalizeDatabaseName($databaseName);

        config(['database.connections.tenant.database' => $n]);
        DB::purge('tenant');

        $runMigrate = function (string $relativePath): void {
            $code = Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => $relativePath,
                '--force' => true,
            ]);
            if ($code !== 0) {
                $out = trim(Artisan::output());
                Log::error('Tenant migrate failed', ['path' => $relativePath, 'code' => $code, 'output' => $out]);
                throw new RuntimeException(
                    'Migration step failed ('.basename($relativePath).'). '.($out !== '' ? $out : 'Exit code '.$code)
                );
            }
        };

        $files = collect(scandir(database_path('migrations')))
            ->filter(fn ($f) => Str::endsWith($f, '.php'))
            ->reject(fn ($f) => in_array($f, self::LANDLORD_MIGRATION_FILES, true))
            ->sort()
            ->values();

        foreach ($files as $file) {
            $runMigrate('database/migrations/'.$file);
        }

        $tenantDir = database_path('migrations/tenant');
        if (is_dir($tenantDir)) {
            $tenantFiles = collect(scandir($tenantDir))
                ->filter(fn ($f) => Str::endsWith($f, '.php'))
                ->sort()
                ->values();
            foreach ($tenantFiles as $file) {
                $runMigrate('database/migrations/tenant/'.$file);
            }
        }
    }

    /** Point all `company_id` values in the tenant DB at the central `companies.id` row. */
    public function syncTenantCompanyIds(string $databaseName, int $companyId): void
    {
        $n = self::normalizeDatabaseName($databaseName);
        config(['database.connections.tenant.database' => $n]);
        DB::purge('tenant');

        $tables = [
            'employee_departments',
            'employee_designations',
            'employees',
            'inventory_categories',
            'inventory_products',
            'inventory_product_uom_conversions',
            'inventory_moves',
            'inventory_cost_layers',
            'inventory_product_favorites',
            'purchase_vendors',
            'purchase_orders',
            'purchase_order_lines',
            'pos_sessions',
            'pos_orders',
            'pos_order_items',
            'pos_payments',
            'pos_cash_movements',
            'expense_categories',
            'expenses',
            'contacts',
            'credit_ledger',
            'calendar_events',
            'manufacturing_boms',
            'manufacturing_bom_lines',
            'manufacturing_orders',
            'report_templates',
            'activity_logs',
            'employee_attendances',
            'payroll_entries',
            'settings',
        ];

        foreach ($tables as $table) {
            if (Schema::connection('tenant')->hasTable($table)
                && Schema::connection('tenant')->hasColumn($table, 'company_id')) {
                DB::connection('tenant')->table($table)->update(['company_id' => $companyId]);
            }
        }
    }
}
