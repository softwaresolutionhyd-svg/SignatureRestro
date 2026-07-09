<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('room_bookings') && ! Schema::hasColumn('room_bookings', 'voucher_no')) {
            Schema::table('room_bookings', function (Blueprint $table) {
                $table->string('voucher_no', 80)->nullable()->after('booking_type')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('room_bookings') && Schema::hasColumn('room_bookings', 'voucher_no')) {
            Schema::table('room_bookings', function (Blueprint $table) {
                $table->dropColumn('voucher_no');
            });
        }
    }
};
