<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('room_rates') || ! Schema::hasColumn('room_rates', 'room_type_id')) {
            return;
        }

        $fkName = $this->foreignKeyName('room_rates', 'room_type_id');
        if ($fkName) {
            Schema::table('room_rates', function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        }

        DB::statement('ALTER TABLE room_rates MODIFY room_type_id BIGINT UNSIGNED NULL');

        Schema::table('room_rates', function (Blueprint $table) {
            if (Schema::hasTable('room_types')) {
                $table->foreign('room_type_id')->references('id')->on('room_types')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('room_rates') || ! Schema::hasColumn('room_rates', 'room_type_id')) {
            return;
        }

        $fkName = $this->foreignKeyName('room_rates', 'room_type_id');
        if ($fkName) {
            Schema::table('room_rates', function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        }

        DB::statement('ALTER TABLE room_rates MODIFY room_type_id BIGINT UNSIGNED NOT NULL');
    }

    private function foreignKeyName(string $table, string $column): ?string
    {
        $db = Schema::getConnection()->getDatabaseName();
        $row = DB::selectOne(
            'SELECT CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            [$db, $table, $column]
        );

        return $row?->name;
    }
};
