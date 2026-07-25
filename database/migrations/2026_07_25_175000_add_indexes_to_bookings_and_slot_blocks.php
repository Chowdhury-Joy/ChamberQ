<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Every other tenant-owned table carries this; bookings was missing
            // it, so nothing could form a composite foreign key back to it.
            $table->unique(['tenant_id', 'id']);

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
            $table->dropUnique(['tenant_id', 'id']);
            $table->dropIndex('bookings_roster_index');
            $table->dropIndex('bookings_bookable_date_index');
        });

        Schema::table('slot_blocks', function (Blueprint $table) {
            $table->dropIndex('slot_blocks_tenant_date_index');
        });
    }
};
