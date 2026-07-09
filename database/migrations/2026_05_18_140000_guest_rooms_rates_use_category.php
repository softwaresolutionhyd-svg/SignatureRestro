<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('room_rates') && ! Schema::hasColumn('room_rates', 'room_category_id')) {
            Schema::table('room_rates', function (Blueprint $table) {
                $table->unsignedBigInteger('room_category_id')->nullable()->after('company_id');
                $table->index('room_category_id');
            });
        }

        if (Schema::hasTable('room_rates') && Schema::hasColumn('room_rates', 'room_type_id')) {
            $rates = DB::table('room_rates')->whereNotNull('room_type_id')->get(['id', 'room_type_id']);
            foreach ($rates as $rate) {
                $categoryId = DB::table('room_types')->where('id', $rate->room_type_id)->value('room_category_id');
                if ($categoryId) {
                    DB::table('room_rates')->where('id', $rate->id)->update(['room_category_id' => $categoryId]);
                }
            }
        }

        if (Schema::hasTable('room_bookings') && ! Schema::hasColumn('room_bookings', 'room_category_id')) {
            Schema::table('room_bookings', function (Blueprint $table) {
                $table->unsignedBigInteger('room_category_id')->nullable()->after('guest_room_id');
                $table->index('room_category_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('room_bookings') && Schema::hasColumn('room_bookings', 'room_category_id')) {
            Schema::table('room_bookings', function (Blueprint $table) {
                $table->dropColumn('room_category_id');
            });
        }

        if (Schema::hasTable('room_rates') && Schema::hasColumn('room_rates', 'room_category_id')) {
            Schema::table('room_rates', function (Blueprint $table) {
                $table->dropColumn('room_category_id');
            });
        }
    }
};
