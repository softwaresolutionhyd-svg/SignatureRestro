<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyInstalledFeature;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

final class TenantFeatureInstaller
{
    public static function install(
        Company $company,
        string $featureKey,
        ?int $installedByUserId,
        ?int $sourceCompanyUpdateId = null
    ): void {
        $pkg = config('company_features.packages.'.$featureKey);
        if (! is_array($pkg)) {
            throw new InvalidArgumentException('Unknown feature package: '.$featureKey);
        }

        if (CompanyInstalledFeature::query()
            ->where('company_id', $company->id)
            ->where('feature_key', $featureKey)
            ->exists()) {
            return;
        }

        $dbName = $company->database_name
            ? TenantDatabaseProvisioner::normalizeDatabaseName($company->database_name)
            : (string) config('database.connections.mysql.database', '');

        if ($dbName === '') {
            throw new RuntimeException('Database name is not configured (.env DB_DATABASE).');
        }
        config(['database.connections.tenant.database' => $dbName]);
        DB::purge('tenant');

        $migrations = $pkg['migrations'] ?? [];
        if ($migrations === []) {
            throw new RuntimeException('Feature package has no migrations: '.$featureKey);
        }

        foreach ($migrations as $relativePath) {
            $code = Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => $relativePath,
                '--force' => true,
            ]);
            if ($code !== 0) {
                $out = trim(Artisan::output());
                Log::error('Tenant feature migrate failed', [
                    'company_id' => $company->id,
                    'feature' => $featureKey,
                    'path' => $relativePath,
                    'code' => $code,
                    'output' => $out,
                ]);
                throw new RuntimeException(
                    'Migration failed: '.($out !== '' ? $out : 'exit '.$code)
                );
            }
        }

        CompanyInstalledFeature::create([
            'company_id' => $company->id,
            'feature_key' => $featureKey,
            'installed_at' => now(),
            'installed_by' => $installedByUserId,
            'source_company_update_id' => $sourceCompanyUpdateId,
        ]);
    }
}
