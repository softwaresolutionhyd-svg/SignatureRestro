<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('users', function (Blueprint $table) {
            if (! Schema::connection('mysql')->hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('password');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('users', function (Blueprint $table) {
            if (Schema::connection('mysql')->hasColumn('users', 'must_change_password')) {
                $table->dropColumn('must_change_password');
            }
        });
    }
};
