<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('report_templates')) {
            return;
        }

        $onTenant = Schema::getConnection()->getName() === 'tenant';

        Schema::create('report_templates', function (Blueprint $table) use ($onTenant) {
            $table->id();
            $table->string('name', 120);
            $table->string('report_type', 30);   // sales|purchases|inventory|employees|expenses|credit
            $table->string('preset', 30)->default('this_month');
            $table->json('cols');                // ["col1","col2",...]
            $table->json('filters')->nullable(); // {"key":"value",...}
            if ($onTenant) {
                $table->unsignedBigInteger('created_by');
            } else {
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            }
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_templates');
    }
};
