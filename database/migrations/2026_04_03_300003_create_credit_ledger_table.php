<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credit_ledger')) {
            return;
        }

        $onTenant = Schema::getConnection()->getName() === 'tenant';

        Schema::create('credit_ledger', function (Blueprint $table) use ($onTenant) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            // type: 'credit' = money owed by contact, 'payment' = contact paid back
            $table->string('type', 20)->default('credit');
            // Link to POS order if this came from POS
            $table->foreignId('pos_order_id')->nullable()->constrained('pos_orders')->nullOnDelete();
            $table->string('description', 300);
            $table->decimal('amount', 14, 2)->default(0);
            // Running balance after this entry (positive = still owes)
            $table->decimal('balance_after', 14, 2)->default(0);
            $table->date('entry_date');
            $table->text('notes')->nullable();
            if ($onTenant) {
                $table->unsignedBigInteger('created_by');
            } else {
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            }
            $table->timestamps();

            $table->index(['contact_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_ledger');
    }
};
