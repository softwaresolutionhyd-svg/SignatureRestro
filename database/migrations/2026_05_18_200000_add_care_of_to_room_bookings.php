<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('room_bookings') && ! Schema::hasColumn('room_bookings', 'care_of')) {
            Schema::table('room_bookings', function (Blueprint $table) {
                $table->string('care_of', 200)->nullable()->after('guest_rank');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('room_bookings') && Schema::hasColumn('room_bookings', 'care_of')) {
            Schema::table('room_bookings', function (Blueprint $table) {
                $table->dropColumn('care_of');
            });
        }
    }
};
