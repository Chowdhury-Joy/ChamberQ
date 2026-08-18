<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_sessions', function (Blueprint $table) {
            $table->index(['tenant_id', 'day_of_week'], 'schedule_sessions_tenant_dow_index');
        });

        Schema::table('lab_collection_slots', function (Blueprint $table) {
            $table->index(['tenant_id', 'day_of_week'], 'lab_slots_tenant_dow_index');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'wants_earlier_date', 'booking_date'],
                'bookings_earlier_date_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('schedule_sessions', function (Blueprint $table) {
            $table->dropIndex('schedule_sessions_tenant_dow_index');
        });

        Schema::table('lab_collection_slots', function (Blueprint $table) {
            $table->dropIndex('lab_slots_tenant_dow_index');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_earlier_date_index');
        });
    }
};
