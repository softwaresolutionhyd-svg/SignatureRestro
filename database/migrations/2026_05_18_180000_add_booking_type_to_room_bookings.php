<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('room_bookings') && ! Schema::hasColumn('room_bookings', 'booking_type')) {
            Schema::table('room_bookings', function (Blueprint $table) {
                $table->string('booking_type', 20)->default('manual')->after('booking_no')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('room_bookings') && Schema::hasColumn('room_bookings', 'booking_type')) {
            Schema::table('room_bookings', function (Blueprint $table) {
                $table->dropColumn('booking_type');
            });
        }
    }
};
