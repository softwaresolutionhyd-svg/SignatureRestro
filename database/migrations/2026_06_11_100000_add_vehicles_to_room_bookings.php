<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('room_bookings') && ! Schema::hasColumn('room_bookings', 'vehicles_count')) {
            Schema::table('room_bookings', function (Blueprint $table) {
                $table->unsignedTinyInteger('vehicles_count')->default(0)->after('children');
            });
        }

        if (Schema::hasTable('room_booking_vehicles')) {
            return;
        }

        Schema::create('room_booking_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_booking_id')->constrained('room_bookings')->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->string('vehicle_no', 40);
            $table->boolean('driver_accompanying')->default(false);
            $table->string('driver_name', 200)->nullable();
            $table->string('driver_cnic', 30)->nullable();
            $table->string('driver_phone', 40)->nullable();
            $table->timestamps();

            $table->index('room_booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_booking_vehicles');

        if (Schema::hasColumn('room_bookings', 'vehicles_count')) {
            Schema::table('room_bookings', function (Blueprint $table) {
                $table->dropColumn('vehicles_count');
            });
        }
    }
};
