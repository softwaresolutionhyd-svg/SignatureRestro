<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('room_rates')) {
            Schema::table('room_rates', function (Blueprint $table) {
                if (! Schema::hasColumn('room_rates', 'person_type')) {
                    $table->string('person_type', 60)->nullable()->after('room_category_id');
                }
                if (! Schema::hasColumn('room_rates', 'room_rent')) {
                    $table->decimal('room_rent', 12, 2)->default(0)->after('person_type');
                }
                if (! Schema::hasColumn('room_rates', 'electric_charges')) {
                    $table->decimal('electric_charges', 12, 2)->default(0)->after('room_rent');
                }
                if (! Schema::hasColumn('room_rates', 'gas_charges')) {
                    $table->decimal('gas_charges', 12, 2)->default(0)->after('electric_charges');
                }
                if (! Schema::hasColumn('room_rates', 'media_charges')) {
                    $table->decimal('media_charges', 12, 2)->default(0)->after('gas_charges');
                }
                if (! Schema::hasColumn('room_rates', 'total')) {
                    $table->decimal('total', 12, 2)->default(0)->after('media_charges');
                }
            });
        }

        if (Schema::hasTable('room_bookings')) {
            Schema::table('room_bookings', function (Blueprint $table) {
                if (! Schema::hasColumn('room_bookings', 'person_type')) {
                    $table->string('person_type', 60)->nullable()->after('room_category_id');
                }
                if (! Schema::hasColumn('room_bookings', 'room_rent')) {
                    $table->decimal('room_rent', 12, 2)->default(0)->after('rate_per_night');
                }
                if (! Schema::hasColumn('room_bookings', 'electric_charges')) {
                    $table->decimal('electric_charges', 12, 2)->default(0)->after('room_rent');
                }
                if (! Schema::hasColumn('room_bookings', 'gas_charges')) {
                    $table->decimal('gas_charges', 12, 2)->default(0)->after('electric_charges');
                }
                if (! Schema::hasColumn('room_bookings', 'media_charges')) {
                    $table->decimal('media_charges', 12, 2)->default(0)->after('gas_charges');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('room_bookings')) {
            Schema::table('room_bookings', function (Blueprint $table) {
                foreach (['person_type', 'room_rent', 'electric_charges', 'gas_charges', 'media_charges'] as $col) {
                    if (Schema::hasColumn('room_bookings', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('room_rates')) {
            Schema::table('room_rates', function (Blueprint $table) {
                foreach (['person_type', 'room_rent', 'electric_charges', 'gas_charges', 'media_charges', 'total'] as $col) {
                    if (Schema::hasColumn('room_rates', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
