<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('menu_deals')) {
            return;
        }

        Schema::create('menu_deals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('name');
            $table->string('sku', 40)->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('is_permanent')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('menu_deal_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('deal_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->decimal('qty', 12, 3)->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_deal_items');
        Schema::dropIfExists('menu_deals');
    }
};
