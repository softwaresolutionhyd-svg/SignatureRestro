<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\TenantDatabaseProvisioner;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs heavy tenant migrations after the HTTP response is sent (see dispatch()->afterResponse()).
 */
class ProvisionCompanyTenantDatabase
{
    use Dispatchable;
    use Queueable;

    public function __construct(
        public int $companyId,
        public string $databaseName,
    ) {}

    public function handle(TenantDatabaseProvisioner $provisioner): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '512M');

        $company = Company::query()->find($this->companyId);
        if (! $company || ! $company->database_name) {
            return;
        }

        $expected = TenantDatabaseProvisioner::normalizeDatabaseName($this->databaseName);
        if (TenantDatabaseProvisioner::normalizeDatabaseName($company->database_name) !== $expected) {
            Log::warning('ProvisionCompanyTenantDatabase: database name mismatch, skipping.', [
                'company_id' => $this->companyId,
            ]);

            return;
        }

        try {
            $provisioner->provision($company->database_name);
            $provisioner->syncTenantCompanyIds($company->database_name, $company->id);
            $company->update([
                'tenant_ready_at' => now(),
                'tenant_provision_failed_at' => null,
                'tenant_provision_error' => null,
            ]);
        } catch (Throwable $e) {
            Log::error('ProvisionCompanyTenantDatabase failed', [
                'company_id' => $company->id,
                'message' => $e->getMessage(),
            ]);
            $company->update([
                'tenant_provision_failed_at' => now(),
                'tenant_provision_error' => Str::limit($e->getMessage(), 500),
            ]);
        }
    }
}
