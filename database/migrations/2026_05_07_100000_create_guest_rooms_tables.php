<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('room_categories')) {
            Schema::create('room_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->default(1)->index();
                $table->string('name', 120);
                $table->string('description', 255)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('active')->default(true)->index();
                $table->timestamps();
                $table->unique(['company_id', 'name']);
            });
        }

        if (! Schema::hasTable('room_types')) {
            Schema::create('room_types', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->default(1)->index();
                $table->foreignId('room_category_id')->nullable()->constrained('room_categories')->nullOnDelete();
                $table->string('name', 120);
                $table->string('code', 30)->nullable();
                $table->unsignedTinyInteger('max_occupancy')->default(2);
                $table->unsignedTinyInteger('bed_count')->default(1);
                $table->string('description', 255)->nullable();
                $table->boolean('active')->default(true)->index();
                $table->timestamps();
                $table->unique(['company_id', 'name']);
            });
        }

        if (! Schema::hasTable('guest_rooms')) {
            Schema::create('guest_rooms', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->default(1)->index();
                $table->string('room_number', 30);
                $table->foreignId('room_type_id')->nullable()->constrained('room_types')->nullOnDelete();
                $table->foreignId('room_category_id')->nullable()->constrained('room_categories')->nullOnDelete();
                $table->string('floor', 20)->nullable();
                $table->string('status', 20)->default('available')->index();
                $table->text('notes')->nullable();
                $table->boolean('active')->default(true)->index();
                $table->timestamps();
                $table->unique(['company_id', 'room_number']);
            });
        }

        if (! Schema::hasTable('room_rates')) {
            Schema::create('room_rates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->default(1)->index();
                $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('rate_type', 20)->default('nightly');
                $table->decimal('amount', 12, 2)->default(0);
                $table->date('valid_from')->nullable();
                $table->date('valid_until')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('room_bookings')) {
            Schema::create('room_bookings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->default(1)->index();
                $table->string('booking_no', 40)->index();
                $table->foreignId('guest_room_id')->nullable()->constrained('guest_rooms')->nullOnDelete();
                $table->foreignId('room_type_id')->nullable()->constrained('room_types')->nullOnDelete();
                $table->foreignId('room_rate_id')->nullable()->constrained('room_rates')->nullOnDelete();
                $table->string('guest_name', 200);
                $table->string('guest_phone', 40)->nullable();
                $table->string('guest_email', 120)->nullable();
                $table->string('guest_cnic', 30)->nullable();
                $table->unsignedTinyInteger('adults')->default(1);
                $table->unsignedTinyInteger('children')->default(0);
                $table->date('check_in_date');
                $table->date('check_out_date');
                $table->dateTime('actual_check_in')->nullable();
                $table->dateTime('actual_check_out')->nullable();
                $table->unsignedSmallInteger('nights')->default(1);
                $table->string('status', 20)->default('reserved')->index();
                $table->decimal('rate_per_night', 12, 2)->default(0);
                $table->decimal('room_charges', 12, 2)->default(0);
                $table->decimal('extra_charges', 12, 2)->default(0);
                $table->decimal('discount', 12, 2)->default(0);
                $table->decimal('tax_percent', 5, 2)->default(0);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->decimal('paid_amount', 12, 2)->default(0);
                $table->decimal('balance', 12, 2)->default(0);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'booking_no']);
            });
        }

        if (! Schema::hasTable('room_booking_charges')) {
            Schema::create('room_booking_charges', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->default(1)->index();
                $table->foreignId('room_booking_id')->constrained('room_bookings')->cascadeOnDelete();
                $table->string('description', 200);
                $table->decimal('amount', 12, 2)->default(0);
                $table->date('charge_date')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('room_bills')) {
            Schema::create('room_bills', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->default(1)->index();
                $table->foreignId('room_booking_id')->constrained('room_bookings')->cascadeOnDelete();
                $table->string('bill_no', 40)->index();
                $table->decimal('room_charges', 12, 2)->default(0);
                $table->decimal('extra_charges', 12, 2)->default(0);
                $table->decimal('discount', 12, 2)->default(0);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->decimal('paid_amount', 12, 2)->default(0);
                $table->decimal('balance', 12, 2)->default(0);
                $table->string('payment_method', 40)->nullable();
                $table->string('payment_status', 20)->default('unpaid')->index();
                $table->dateTime('billed_at')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'bill_no']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('room_bills');
        Schema::dropIfExists('room_booking_charges');
        Schema::dropIfExists('room_bookings');
        Schema::dropIfExists('room_rates');
        Schema::dropIfExists('guest_rooms');
        Schema::dropIfExists('room_types');
        Schema::dropIfExists('room_categories');
    }
};
