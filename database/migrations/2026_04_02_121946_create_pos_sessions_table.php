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
        if (Schema::hasTable('pos_sessions')) {
            return;
        }

        $onTenant = Schema::getConnection()->getName() === 'tenant';

        Schema::create('pos_sessions', function (Blueprint $table) use ($onTenant) {
            $table->id();
            $table->string('session_no', 40)->unique();
            if ($onTenant) {
                $table->unsignedBigInteger('user_id');
            } else {
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            }
            $table->string('status', 20)->default('open')->index(); // open|closed
            $table->decimal('opening_cash', 14, 2)->default(0);
            $table->decimal('closing_cash', 14, 2)->nullable();
            $table->decimal('expected_cash', 14, 2)->nullable();
            $table->decimal('cash_difference', 14, 2)->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_sessions');
    }
};
