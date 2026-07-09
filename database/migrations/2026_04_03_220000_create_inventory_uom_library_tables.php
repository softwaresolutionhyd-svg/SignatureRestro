<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_units')) {
            Schema::create('inventory_units', function (Blueprint $table) {
                $table->id();
                $table->string('code', 30)->unique();
                $table->string('name', 120);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inventory_unit_conversions')) {
            Schema::create('inventory_unit_conversions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('from_unit_id')->constrained('inventory_units')->cascadeOnDelete();
                $table->foreignId('to_unit_id')->constrained('inventory_units')->restrictOnDelete();
                $table->decimal('factor', 24, 12);
                $table->string('note', 255)->nullable();
                $table->timestamps();

                $table->unique(['from_unit_id', 'to_unit_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_unit_conversions');
        Schema::dropIfExists('inventory_units');
    }
};
