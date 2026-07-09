<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manufacturing_bom_lines', function (Blueprint $table) {
            $table->string('uom', 30)->nullable()->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('manufacturing_bom_lines', function (Blueprint $table) {
            $table->dropColumn('uom');
        });
    }
};
