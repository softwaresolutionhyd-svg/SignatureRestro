<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('room_booking_charges')) {
            return;
        }

        Schema::table('room_booking_charges', function (Blueprint $table) {
            if (! Schema::hasColumn('room_booking_charges', 'charge_type')) {
                $table->string('charge_type', 20)->nullable()->after('room_booking_id');
            }
            if (! Schema::hasColumn('room_booking_charges', 'unit_amount')) {
                $table->decimal('unit_amount', 12, 2)->nullable()->after('amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('room_booking_charges')) {
            return;
        }

        Schema::table('room_booking_charges', function (Blueprint $table) {
            if (Schema::hasColumn('room_booking_charges', 'unit_amount')) {
                $table->dropColumn('unit_amount');
            }
            if (Schema::hasColumn('room_booking_charges', 'charge_type')) {
                $table->dropColumn('charge_type');
            }
        });
    }
};
