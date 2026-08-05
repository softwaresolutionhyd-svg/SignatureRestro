<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait EnsuresEmployeePhotoSchema
{
    protected function ensureEmployeePhotoSchema(?string $connection = null): void
    {
        $schema = Schema::connection($connection ?? 'tenant');

        if (! $schema->hasTable('employees') || $schema->hasColumn('employees', 'photo_path')) {
            return;
        }

        $schema->table('employees', function (Blueprint $table) {
            $table->string('photo_path', 255)->nullable()->after('address');
        });
    }
}
