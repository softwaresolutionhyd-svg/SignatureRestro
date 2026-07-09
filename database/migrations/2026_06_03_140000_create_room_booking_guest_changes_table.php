<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('room_booking_guest_changes')) {
            return;
        }

        Schema::create('room_booking_guest_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1)->index();
            $table->foreignId('room_booking_id')->constrained('room_bookings')->cascadeOnDelete();
            $table->string('field', 40);
            $table->string('field_label', 80)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable()->index();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['room_booking_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_booking_guest_changes');
    }
};
