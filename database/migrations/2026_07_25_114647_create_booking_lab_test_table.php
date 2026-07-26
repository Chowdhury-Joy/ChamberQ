<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_lab_test', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->uuid('booking_id');
            $table->unsignedBigInteger('lab_test_id');

            // Test prices change. A booking made last month must still show what
            // the patient was actually quoted, so each line item carries its own
            // copy of the price at the moment of booking.
            $table->decimal('price_at_booking', 10, 2);

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            // Composite foreign keys: a line item can never reference another
            // tenant's booking or another tenant's test, even if the application
            // layer is bypassed.
            $table->foreign(['tenant_id', 'booking_id'])
                ->references(['tenant_id', 'id'])->on('bookings')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'lab_test_id'])
                ->references(['tenant_id', 'id'])->on('lab_tests')->cascadeOnDelete();

            $table->unique(['booking_id', 'lab_test_id']);
            $table->index(['tenant_id', 'booking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_lab_test');
    }
};
