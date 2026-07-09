<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('room_person_types')) {
            Schema::create('room_person_types', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->default(1)->index();
                $table->string('name', 60);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('active')->default(true)->index();
                $table->timestamps();
                $table->unique(['company_id', 'name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('room_person_types');
    }
};
