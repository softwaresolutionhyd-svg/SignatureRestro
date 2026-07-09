<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        if (! Schema::hasColumn('companies', 'tenant_ready_at')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->timestamp('tenant_ready_at')->nullable()->after('database_name');
                $table->timestamp('tenant_provision_failed_at')->nullable()->after('tenant_ready_at');
                $table->string('tenant_provision_error', 500)->nullable()->after('tenant_provision_failed_at');
            });
        }

        // Existing rows that already have a dedicated DB were provisioned before this column existed.
        DB::table('companies')
            ->whereNotNull('database_name')
            ->whereNull('tenant_ready_at')
            ->update(['tenant_ready_at' => now()]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasColumn('companies', 'tenant_ready_at')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['tenant_ready_at', 'tenant_provision_failed_at', 'tenant_provision_error']);
        });
    }
};
