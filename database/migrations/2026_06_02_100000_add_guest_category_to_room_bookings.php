<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('room_bookings') && ! Schema::hasColumn('room_bookings', 'guest_category')) {
            Schema::table('room_bookings', function (Blueprint $table) {
                $table->string('guest_category', 10)->nullable()->after('person_type')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('room_bookings') && Schema::hasColumn('room_bookings', 'guest_category')) {
            Schema::table('room_bookings', function (Blueprint $table) {
                $table->dropColumn('guest_category');
            });
        }
    }
};
