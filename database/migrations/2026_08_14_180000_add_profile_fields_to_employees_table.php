<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'father_name')) {
                $table->string('father_name', 150)->nullable();
            }
            if (! Schema::hasColumn('employees', 'cnic')) {
                $table->string('cnic', 30)->nullable();
            }
            if (! Schema::hasColumn('employees', 'city')) {
                $table->string('city', 100)->nullable();
            }
            if (! Schema::hasColumn('employees', 'district')) {
                $table->string('district', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            foreach (['father_name', 'cnic', 'city', 'district'] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
