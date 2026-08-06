<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 512);
            $table->string('platform', 32)->default('android'); // android|ios|web
            $table->string('app', 32)->default('admin'); // admin|order-taker
            $table->string('device_id', 191)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique('token');
            $table->index(['user_id', 'app']);
            $table->index(['app', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('device_tokens');
    }
};
