<?php

namespace App\Services\Sync;

use App\Support\EnsuresEmployeeLoanSchema;
use App\Support\EnsuresEmployeeStaffCategorySchema;
use App\Support\EnsuresPayrollSchema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncTargetSchemaService
{
    use EnsuresEmployeeLoanSchema;
    use EnsuresEmployeeStaffCategorySchema;
    use EnsuresPayrollSchema;

    public function ensureAll(): void
    {
        foreach ($this->connectionNames() as $connection) {
            try {
                $this->ensurePayrollSchema($connection);
                $this->ensureEmployeeLoanSchema($connection);
                $this->ensureEmployeeStaffCategorySchema($connection);
                $this->ensureCreditLedgerPayrollColumn($connection);
            } catch (\Throwable $e) {
                Log::warning('sync.schema_ensure_failed', [
                    'connection' => $connection,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function connectionNames(): array
    {
        $names = array_values(array_unique(array_filter([
            (string) config('database.default'),
            'mysql',
            'tenant',
        ])));

        return array_values(array_filter(
            $names,
            fn (string $name) => is_array(config("database.connections.{$name}"))
        ));
    }

    private function ensureCreditLedgerPayrollColumn(string $connection): void
    {
        $schema = Schema::connection($connection);

        if (! $schema->hasTable('credit_ledger') || ! $schema->hasTable('payroll_entries')) {
            return;
        }

        if ($schema->hasColumn('credit_ledger', 'payroll_entry_id')) {
            return;
        }

        $schema->table('credit_ledger', function ($table) {
            $table->unsignedBigInteger('payroll_entry_id')->nullable()->after('pos_order_id');
            $table->index('payroll_entry_id');
        });
    }
}
