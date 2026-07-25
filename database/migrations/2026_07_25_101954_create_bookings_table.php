<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('bookable_type');
            $table->unsignedBigInteger('bookable_id');
            $table->date('booking_date');
            $table->string('patient_name');
            $table->string('patient_phone');
            $table->integer('serial_number');
            $table->string('status')->default('waiting'); // waiting, in_chamber, completed, cancelled
            $table->string('payment_status')->default('unpaid');
            $table->string('payment_reference')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            // Note: because bookable_id references an integer ID on either schedule_sessions or lab_collections,
            // we do a composite unique constraint here.
            $table->unique(['tenant_id', 'bookable_type', 'bookable_id', 'booking_date', 'serial_number'], 'bookings_serial_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
