<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('room_booking_guest_room')) {
            return;
        }

        Schema::create('room_booking_guest_room', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_booking_id')->constrained('room_bookings')->cascadeOnDelete();
            $table->foreignId('guest_room_id')->constrained('guest_rooms')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['room_booking_id', 'guest_room_id']);
        });

        if (Schema::hasTable('room_bookings') && Schema::hasColumn('room_bookings', 'guest_room_id')) {
            $rows = DB::table('room_bookings')
                ->whereNotNull('guest_room_id')
                ->select('id', 'guest_room_id')
                ->get();

            foreach ($rows as $row) {
                DB::table('room_booking_guest_room')->insertOrIgnore([
                    'room_booking_id' => $row->id,
                    'guest_room_id' => $row->guest_room_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('room_booking_guest_room');
    }
};
