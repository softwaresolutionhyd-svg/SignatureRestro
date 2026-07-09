<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('guest_rooms', function (Blueprint $table) {
            if (! Schema::connection('tenant')->hasColumn('guest_rooms', 'maintenance_cost')) {
                $table->decimal('maintenance_cost', 12, 2)->nullable()->after('maintenance_notes');
            }
            if (! Schema::connection('tenant')->hasColumn('guest_rooms', 'maintenance_bill_reference')) {
                $table->string('maintenance_bill_reference', 120)->nullable()->after('maintenance_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('guest_rooms', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('guest_rooms', 'maintenance_bill_reference')) {
                $table->dropColumn('maintenance_bill_reference');
            }
            if (Schema::connection('tenant')->hasColumn('guest_rooms', 'maintenance_cost')) {
                $table->dropColumn('maintenance_cost');
            }
        });
    }
};
