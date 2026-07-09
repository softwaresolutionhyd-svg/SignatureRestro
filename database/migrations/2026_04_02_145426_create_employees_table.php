<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            return;
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_no', 40)->unique();
            $table->string('name', 150);
            $table->string('email', 200)->nullable()->index();
            $table->string('phone', 60)->nullable();
            $table->string('department', 120)->nullable()->index();
            $table->string('designation', 120)->nullable()->index();
            $table->date('join_date')->nullable()->index();
            $table->decimal('salary', 14, 2)->default(0);
            $table->string('address', 255)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->index(['name', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
