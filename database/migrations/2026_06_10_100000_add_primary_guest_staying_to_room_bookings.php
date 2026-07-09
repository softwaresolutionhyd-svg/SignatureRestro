<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('room_bookings')) {
            return;
        }

        if (Schema::hasColumn('room_bookings', 'primary_guest_staying')) {
            return;
        }

        Schema::table('room_bookings', function (Blueprint $table) {
            $table->boolean('primary_guest_staying')->default(true)->after('guest_cnic');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('room_bookings', 'primary_guest_staying')) {
            return;
        }

        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropColumn('primary_guest_staying');
        });
    }
};
