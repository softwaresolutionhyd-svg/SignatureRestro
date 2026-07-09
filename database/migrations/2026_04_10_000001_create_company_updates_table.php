<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->create('company_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->string('version', 50)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('company_updates');
    }
};
