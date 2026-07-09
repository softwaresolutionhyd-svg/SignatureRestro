<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guest_rooms')) {
            return;
        }

        Schema::table('guest_rooms', function (Blueprint $table) {
            if (! Schema::hasColumn('guest_rooms', 'maintenance_reason')) {
                $table->string('maintenance_reason', 40)->nullable()->after('cleaning_started_at');
            }
            if (! Schema::hasColumn('guest_rooms', 'maintenance_notes')) {
                $table->text('maintenance_notes')->nullable()->after('maintenance_reason');
            }
            if (! Schema::hasColumn('guest_rooms', 'maintenance_started_at')) {
                $table->timestamp('maintenance_started_at')->nullable()->after('maintenance_notes');
            }
            if (! Schema::hasColumn('guest_rooms', 'maintenance_checklist')) {
                $table->json('maintenance_checklist')->nullable()->after('maintenance_started_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('guest_rooms')) {
            return;
        }

        Schema::table('guest_rooms', function (Blueprint $table) {
            foreach (['maintenance_checklist', 'maintenance_started_at', 'maintenance_notes', 'maintenance_reason'] as $col) {
                if (Schema::hasColumn('guest_rooms', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
