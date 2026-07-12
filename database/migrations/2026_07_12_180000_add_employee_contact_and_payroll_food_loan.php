<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees') && ! Schema::hasColumn('employees', 'contact_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->foreignId('contact_id')->nullable()->after('user_id')->constrained('contacts')->nullOnDelete();
            });
        }

        if (Schema::hasTable('payroll_entries')) {
            Schema::table('payroll_entries', function (Blueprint $table) {
                if (! Schema::hasColumn('payroll_entries', 'food_bill')) {
                    $table->decimal('food_bill', 14, 2)->default(0)->after('deduction');
                }
                if (! Schema::hasColumn('payroll_entries', 'loan')) {
                    $table->decimal('loan', 14, 2)->default(0)->after('food_bill');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'contact_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropForeign(['contact_id']);
                $table->dropColumn('contact_id');
            });
        }

        if (Schema::hasTable('payroll_entries')) {
            Schema::table('payroll_entries', function (Blueprint $table) {
                if (Schema::hasColumn('payroll_entries', 'loan')) {
                    $table->dropColumn('loan');
                }
                if (Schema::hasColumn('payroll_entries', 'food_bill')) {
                    $table->dropColumn('food_bill');
                }
            });
        }
    }
};
