<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('expenses') && ! $schema->hasColumn('expenses', 'payment_type')) {
            $schema->table('expenses', function (Blueprint $table) {
                $table->string('payment_type', 20)->default('debit')->after('expense_date');
            });
        }

        if ($schema->hasTable('expense_lines') && ! $schema->hasColumn('expense_lines', 'category_id')) {
            $schema->table('expense_lines', function (Blueprint $table) {
                $table->unsignedBigInteger('category_id')->nullable()->after('description');
                $table->index('category_id');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('expenses') && $schema->hasColumn('expenses', 'payment_type')) {
            $schema->table('expenses', function (Blueprint $table) {
                $table->dropColumn('payment_type');
            });
        }

        if ($schema->hasTable('expense_lines') && $schema->hasColumn('expense_lines', 'category_id')) {
            $schema->table('expense_lines', function (Blueprint $table) {
                $table->dropIndex(['category_id']);
                $table->dropColumn('category_id');
            });
        }
    }
};
