<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('calendar_events')) {
            return;
        }

        $onTenant = Schema::getConnection()->getName() === 'tenant';

        Schema::create('calendar_events', function (Blueprint $table) use ($onTenant) {
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('location', 255)->nullable();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->boolean('all_day')->default(false);
            // meeting | task | holiday | reminder | other
            $table->string('event_type', 30)->default('meeting');
            // hex colour chosen by user / event_type default
            $table->string('color', 20)->default('#7c3aed');
            if ($onTenant) {
                $table->unsignedBigInteger('created_by');
            } else {
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            }
            $table->timestamps();

            $table->index(['start_datetime', 'end_datetime']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
