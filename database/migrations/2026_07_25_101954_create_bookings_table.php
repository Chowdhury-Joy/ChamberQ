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
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            // Declared here, not in a later migration: `booking_lab_test` forms
            // a composite FK against (tenant_id, id), and MySQL requires the
            // referenced unique key to already exist when that FK is created
            // ("6125 Missing unique key for constraint"). SQLite does not check,
            // so adding it later appeared to work right up until the first
            // MySQL install.
            $table->unique(['tenant_id', 'id']);

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
