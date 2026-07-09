<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contacts')) {
            return;
        }

        if (Schema::hasColumn('contacts', 'category')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $table) {
            $table->string('category', 40)->nullable()->after('name')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contacts') || ! Schema::hasColumn('contacts', 'category')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
