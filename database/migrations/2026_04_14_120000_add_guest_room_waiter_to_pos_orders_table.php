<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_orders')) {
            return;
        }

        $addGuest = ! Schema::hasColumn('pos_orders', 'guest_name');
        $addRoom = ! Schema::hasColumn('pos_orders', 'room_no');
        $addWaiter = ! Schema::hasColumn('pos_orders', 'waiter_name');

        if (! $addGuest && ! $addRoom && ! $addWaiter) {
            return;
        }

        Schema::table('pos_orders', function (Blueprint $table) use ($addGuest, $addRoom, $addWaiter) {
            if ($addGuest) {
                $table->string('guest_name', 120)->nullable()->after('contact_id');
            }
            if ($addRoom) {
                $table->string('room_no', 50)->nullable()->after('guest_name');
            }
            if ($addWaiter) {
                $table->string('waiter_name', 120)->nullable()->after('room_no');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_orders')) {
            return;
        }

        $dropWaiter = Schema::hasColumn('pos_orders', 'waiter_name');
        $dropRoom = Schema::hasColumn('pos_orders', 'room_no');
        $dropGuest = Schema::hasColumn('pos_orders', 'guest_name');

        if (! $dropWaiter && ! $dropRoom && ! $dropGuest) {
            return;
        }

        Schema::table('pos_orders', function (Blueprint $table) use ($dropWaiter, $dropRoom, $dropGuest) {
            if ($dropWaiter) {
                $table->dropColumn('waiter_name');
            }
            if ($dropRoom) {
                $table->dropColumn('room_no');
            }
            if ($dropGuest) {
                $table->dropColumn('guest_name');
            }
        });
    }
};
