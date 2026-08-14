<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        if (! Schema::hasColumn('employees', 'qr_token')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('qr_token', 64)->nullable();
            });
        }

        $ids = DB::table('employees')
            ->where(function ($q) {
                $q->whereNull('qr_token')->orWhere('qr_token', '');
            })
            ->pluck('id');

        foreach ($ids as $id) {
            DB::table('employees')->where('id', $id)->update([
                'qr_token' => bin2hex(random_bytes(32)),
            ]);
        }

        try {
            Schema::table('employees', function (Blueprint $table) {
                $table->unique('qr_token');
            });
        } catch (Throwable) {
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees') || ! Schema::hasColumn('employees', 'qr_token')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            try {
                $table->dropUnique(['qr_token']);
            } catch (Throwable) {
            }
            $table->dropColumn('qr_token');
        });
    }
};
