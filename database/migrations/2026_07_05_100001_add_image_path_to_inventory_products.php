<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_products')) {
            return;
        }

        if (Schema::hasColumn('inventory_products', 'image_path')) {
            return;
        }

        Schema::table('inventory_products', function (Blueprint $table) {
            $table->string('image_path', 500)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_products') && Schema::hasColumn('inventory_products', 'image_path')) {
            Schema::table('inventory_products', function (Blueprint $table) {
                $table->dropColumn('image_path');
            });
        }
    }
};
