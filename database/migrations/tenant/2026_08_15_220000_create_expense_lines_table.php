<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');
        if ($schema->hasTable('expense_lines')) {
            return;
        }

        $schema->create('expense_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->string('description', 255);
            $table->decimal('qty', 10, 3)->default(1);
            $table->decimal('unit_amount', 14, 2)->default(0);
            $table->decimal('tax_percent', 6, 3)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['expense_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('expense_lines');
    }
};
