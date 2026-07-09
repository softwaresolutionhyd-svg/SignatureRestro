<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expense_categories')) {
            return;
        }

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
