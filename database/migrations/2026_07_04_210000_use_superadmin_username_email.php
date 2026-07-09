<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('mysql')->hasTable('users')) {
            return;
        }

        $exists = DB::connection('mysql')->table('users')
            ->where('email', 'superadmin@example.com')
            ->exists();

        if (! $exists) {
            return;
        }

        $taken = DB::connection('mysql')->table('users')
            ->where('email', 'superadmin')
            ->exists();

        if ($taken) {
            return;
        }

        DB::connection('mysql')->table('users')
            ->where('email', 'superadmin@example.com')
            ->update(['email' => 'superadmin']);
    }

    public function down(): void
    {
        if (! Schema::connection('mysql')->hasTable('users')) {
            return;
        }

        DB::connection('mysql')->table('users')
            ->where('email', 'superadmin')
            ->where('role', 'super_admin')
            ->update(['email' => 'superadmin@example.com']);
    }
};
