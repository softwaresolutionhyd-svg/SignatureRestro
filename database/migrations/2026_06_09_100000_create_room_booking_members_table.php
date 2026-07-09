<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('room_booking_members')) {
            return;
        }

        Schema::create('room_booking_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_booking_id')->constrained('room_bookings')->cascadeOnDelete();
            $table->string('member_type', 10);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->string('name', 200);
            $table->string('cnic', 30)->nullable();
            $table->string('relation', 100)->nullable();
            $table->timestamps();

            $table->index(['room_booking_id', 'member_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_booking_members');
    }
};
