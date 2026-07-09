<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        if (! Schema::hasColumn('companies', 'database_name')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('database_name', 64)->nullable()->unique()->after('slug');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'database_name')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropUnique(['database_name']);
                $table->dropColumn('database_name');
            });
        }
    }
};
