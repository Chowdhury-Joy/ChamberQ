<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // The (tenant_id, id) unique key that composite foreign keys need
            // was moved into the create-bookings migration — `booking_lab_test`
            // references it and runs before this file, which MySQL rejects.
            // Do not re-add it here.

            // Daily roster: tenant + today + status ordering.
            $table->index(['tenant_id', 'booking_date', 'status'], 'bookings_roster_index');

            // Capacity counting and serial allocation on the booking hot path.
            $table->index(['bookable_type', 'bookable_id', 'booking_date'], 'bookings_bookable_date_index');
        });

        Schema::table('slot_blocks', function (Blueprint $table) {
            // Checked on every booking attempt.
            $table->index(['tenant_id', 'date'], 'slot_blocks_tenant_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_roster_index');
            $table->dropIndex('bookings_bookable_date_index');
        });

        Schema::table('slot_blocks', function (Blueprint $table) {
            $table->dropIndex('slot_blocks_tenant_date_index');
        });
    }
};
