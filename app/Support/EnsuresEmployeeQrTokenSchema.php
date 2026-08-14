<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

trait EnsuresEmployeeQrTokenSchema
{
    private static bool $qrTokenSchemaReady = false;

    public static function ensureQrTokenSchema(?string $connection = null): void
    {
        if (self::$qrTokenSchemaReady) {
            return;
        }

        $connection = $connection ?? 'tenant';
        $schema = Schema::connection($connection);

        if (! $schema->hasTable('employees')) {
            return;
        }

        if (! $schema->hasColumn('employees', 'qr_token')) {
            $schema->table('employees', function (Blueprint $table) {
                $table->string('qr_token', 64)->nullable();
            });
        }

        $db = DB::connection($connection);
        $ids = $db->table('employees')
            ->where(function ($q) {
                $q->whereNull('qr_token')->orWhere('qr_token', '');
            })
            ->pluck('id');

        foreach ($ids as $id) {
            $db->table('employees')->where('id', $id)->update([
                'qr_token' => bin2hex(random_bytes(32)),
            ]);
        }

        try {
            $schema->table('employees', function (Blueprint $table) {
                $table->unique('qr_token');
            });
        } catch (Throwable) {
        }

        self::$qrTokenSchemaReady = true;
    }
}
