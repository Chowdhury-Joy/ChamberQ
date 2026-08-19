<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->index(['tenant_id', 'session_date', 'status'], 'live_sessions_tenant_date_status_idx');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'bookable_type', 'bookable_id', 'booking_date', 'status', 'serial_number'],
                'bookings_queue_advance_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropIndex('live_sessions_tenant_date_status_idx');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_queue_advance_idx');
        });
    }
};
