<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('room_booking_guest_room')) {
            return;
        }

        if (! Schema::hasColumn('room_booking_guest_room', 'released_at')) {
            Schema::table('room_booking_guest_room', function (Blueprint $table) {
                $table->dateTime('released_at')->nullable()->after('guest_room_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('room_booking_guest_room') && Schema::hasColumn('room_booking_guest_room', 'released_at')) {
            Schema::table('room_booking_guest_room', function (Blueprint $table) {
                $table->dropColumn('released_at');
            });
        }
    }
};
