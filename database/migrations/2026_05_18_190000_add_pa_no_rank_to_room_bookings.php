<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('room_bookings')) {
            Schema::table('room_bookings', function (Blueprint $table) {
                if (! Schema::hasColumn('room_bookings', 'pa_no')) {
                    $table->string('pa_no', 40)->nullable()->after('booking_type');
                }
                if (! Schema::hasColumn('room_bookings', 'guest_rank')) {
                    $table->string('guest_rank', 60)->nullable()->after('pa_no');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('room_bookings')) {
            Schema::table('room_bookings', function (Blueprint $table) {
                foreach (['pa_no', 'guest_rank'] as $col) {
                    if (Schema::hasColumn('room_bookings', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
