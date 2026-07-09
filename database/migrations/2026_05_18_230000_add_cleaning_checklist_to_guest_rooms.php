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
            if (! Schema::hasColumn('guest_rooms', 'cleaning_checklist')) {
                $table->json('cleaning_checklist')->nullable()->after('status');
            }
            if (! Schema::hasColumn('guest_rooms', 'cleaning_started_at')) {
                $table->timestamp('cleaning_started_at')->nullable()->after('cleaning_checklist');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('guest_rooms')) {
            return;
        }

        Schema::table('guest_rooms', function (Blueprint $table) {
            if (Schema::hasColumn('guest_rooms', 'cleaning_started_at')) {
                $table->dropColumn('cleaning_started_at');
            }
            if (Schema::hasColumn('guest_rooms', 'cleaning_checklist')) {
                $table->dropColumn('cleaning_checklist');
            }
        });
    }
};
