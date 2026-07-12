<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('employees') && ! $schema->hasColumn('employees', 'contact_id')) {
            $schema->table('employees', function (Blueprint $table) {
                $table->foreignId('contact_id')->nullable()->after('user_id')->constrained('contacts')->nullOnDelete();
            });
        }

        if ($schema->hasTable('payroll_entries')) {
            $schema->table('payroll_entries', function (Blueprint $table) use ($schema) {
                if (! $schema->hasColumn('payroll_entries', 'food_bill')) {
                    $table->decimal('food_bill', 14, 2)->default(0)->after('deduction');
                }
                if (! $schema->hasColumn('payroll_entries', 'loan')) {
                    $table->decimal('loan', 14, 2)->default(0)->after('food_bill');
                }
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('employees') && $schema->hasColumn('employees', 'contact_id')) {
            $schema->table('employees', function (Blueprint $table) {
                $table->dropForeign(['contact_id']);
                $table->dropColumn('contact_id');
            });
        }

        if ($schema->hasTable('payroll_entries')) {
            $schema->table('payroll_entries', function (Blueprint $table) use ($schema) {
                if ($schema->hasColumn('payroll_entries', 'loan')) {
                    $table->dropColumn('loan');
                }
                if ($schema->hasColumn('payroll_entries', 'food_bill')) {
                    $table->dropColumn('food_bill');
                }
            });
        }
    }
};
