<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_queue', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 128);
            $table->string('record_key', 64);
            $table->string('action', 16); // upsert | delete
            $table->json('payload')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['synced_at', 'id']);
            $table->index(['table_name', 'record_key', 'synced_at']);
        });

        Schema::create('sync_meta', function (Blueprint $table) {
            $table->string('meta_key', 64)->primary();
            $table->text('meta_value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_queue');
        Schema::dropIfExists('sync_meta');
    }
};
