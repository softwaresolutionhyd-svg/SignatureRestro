<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait EnsuresKitchenAgentSchema
{
    protected function ensureKitchenAgentSchema(?string $connection = null): void
    {
        $schema = Schema::connection($connection ?? 'tenant');

        if (! $schema->hasTable('inventory_departments')) {
            return;
        }

        if (! $schema->hasColumn('inventory_departments', 'printer_ip')) {
            $schema->table('inventory_departments', function (Blueprint $table) {
                $table->string('printer_ip', 45)->nullable()->after('is_warehouse');
            });
        }

        if (! $schema->hasColumn('inventory_departments', 'printer_port')) {
            $schema->table('inventory_departments', function (Blueprint $table) {
                $table->unsignedInteger('printer_port')->nullable()->after('printer_ip');
            });
        }

        if (! $schema->hasColumn('inventory_departments', 'printer_name')) {
            $schema->table('inventory_departments', function (Blueprint $table) {
                $table->string('printer_name', 100)->nullable()->after('printer_port');
            });
        }
    }
}
