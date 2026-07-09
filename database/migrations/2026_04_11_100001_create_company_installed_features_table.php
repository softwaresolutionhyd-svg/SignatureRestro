<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->create('company_installed_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('feature_key', 80);
            $table->timestamp('installed_at')->useCurrent();
            $table->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_company_update_id')->nullable()->constrained('company_updates')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('company_installed_features');
    }
};
