<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guest_rooms')) {
            return;
        }

        DB::connection('tenant')->table('guest_rooms')
            ->where('status', 'maintenance')
            ->update([
                'status' => 'available',
                'maintenance_reason' => null,
                'maintenance_notes' => null,
                'maintenance_started_at' => null,
                'maintenance_checklist' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Not reversible
    }
};
