<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('code', 20);
                $table->string('name', 150);
                $table->string('type', 20)->index(); // asset, liability, equity, income, expense
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('description', 500)->nullable();
                $table->boolean('active')->default(true);
                $table->boolean('is_system')->default(false);
                $table->timestamps();

                $table->unique(['company_id', 'code']);
                $table->index(['company_id', 'type']);
            });
        }

        if (! Schema::hasTable('journal_entries')) {
            $onTenant = Schema::getConnection()->getName() === 'tenant';

            Schema::create('journal_entries', function (Blueprint $table) use ($onTenant) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('entry_number', 30);
                $table->date('entry_date')->index();
                $table->string('reference', 100)->nullable();
                $table->string('description', 500)->nullable();
                $table->string('status', 20)->default('draft')->index(); // draft, posted
                $table->string('source', 30)->default('manual'); // manual, expense, pos, purchase, payroll
                $table->unsignedBigInteger('source_id')->nullable();
                $table->timestamp('posted_at')->nullable();
                if ($onTenant) {
                    $table->unsignedBigInteger('posted_by')->nullable();
                } else {
                    $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                }
                $table->decimal('total_debit', 16, 2)->default(0);
                $table->decimal('total_credit', 16, 2)->default(0);
                $table->timestamps();

                $table->unique(['company_id', 'entry_number']);
            });
        }

        if (! Schema::hasTable('journal_entry_lines')) {
            Schema::create('journal_entry_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
                $table->string('description', 500)->nullable();
                $table->decimal('debit', 16, 2)->default(0);
                $table->decimal('credit', 16, 2)->default(0);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['journal_entry_id', 'account_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
