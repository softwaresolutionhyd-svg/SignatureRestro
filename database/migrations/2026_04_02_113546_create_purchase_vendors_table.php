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
        if (Schema::hasTable('purchase_vendors')) {
            return;
        }

        Schema::create('purchase_vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('email', 200)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('tax_id', 80)->nullable();
            $table->text('address')->nullable();
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
        Schema::dropIfExists('purchase_vendors');
    }
};
