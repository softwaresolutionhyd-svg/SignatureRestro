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
        if (Schema::hasTable('pos_cash_movements')) {
            return;
        }

        $onTenant = Schema::getConnection()->getName() === 'tenant';

        Schema::create('pos_cash_movements', function (Blueprint $table) use ($onTenant) {
            $table->id();
            $table->foreignId('session_id')->constrained('pos_sessions')->cascadeOnDelete();
            if ($onTenant) {
                $table->unsignedBigInteger('user_id')->nullable();
            } else {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            }
            $table->string('type', 20); // in|out
            $table->decimal('amount', 14, 2);
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_cash_movements');
    }
};
