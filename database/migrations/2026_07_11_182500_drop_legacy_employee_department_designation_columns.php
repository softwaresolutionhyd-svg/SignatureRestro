<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('tenant')->hasTable('employees')) {
            return;
        }

        Schema::connection('tenant')->table('employees', function (Blueprint $table) {
            if (Schema::connection('tenant')->hasColumn('employees', 'designation')) {
                $table->dropColumn('designation');
            }
            if (Schema::connection('tenant')->hasColumn('employees', 'department')) {
                $table->dropColumn('department');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection('tenant')->hasTable('employees')) {
            return;
        }

        Schema::connection('tenant')->table('employees', function (Blueprint $table) {
            if (! Schema::connection('tenant')->hasColumn('employees', 'department')) {
                $table->string('department', 120)->nullable()->index()->after('phone');
            }
            if (! Schema::connection('tenant')->hasColumn('employees', 'designation')) {
                $table->string('designation', 120)->nullable()->index()->after('department');
            }
        });
    }
};
