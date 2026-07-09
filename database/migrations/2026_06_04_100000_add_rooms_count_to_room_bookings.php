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
        if (Schema::hasColumn('room_bookings', 'rooms_count')) {
            return;
        }

        Schema::table('room_bookings', function (Blueprint $table) {
            $table->unsignedTinyInteger('rooms_count')->default(1)->after('children');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('room_bookings') && Schema::hasColumn('room_bookings', 'rooms_count')) {
            Schema::table('room_bookings', function (Blueprint $table) {
                $table->dropColumn('rooms_count');
            });
        }
    }
};
