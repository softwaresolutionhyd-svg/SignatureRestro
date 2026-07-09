<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name', 150);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'active']);
        });

        Schema::table('inventory_products', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('category_id')
                ->constrained('inventory_departments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::dropIfExists('inventory_departments');
    }
};
