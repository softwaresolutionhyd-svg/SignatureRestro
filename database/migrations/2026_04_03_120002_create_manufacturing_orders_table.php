<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('manufacturing_orders')) {
            return;
        }

        $onTenant = Schema::getConnection()->getName() === 'tenant';

        Schema::create('manufacturing_orders', function (Blueprint $table) use ($onTenant) {
            $table->id();
            $table->foreignId('bom_id')->constrained('manufacturing_boms')->restrictOnDelete();
            if ($onTenant) {
                $table->unsignedBigInteger('user_id')->nullable();
            } else {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            }
            $table->decimal('qty_ordered', 14, 3);
            $table->string('status', 20)->default('draft')->index();
            $table->string('reference', 80)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturing_orders');
    }
};
