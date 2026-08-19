<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->index(['tenant_id', 'session_date', 'status'], 'live_sessions_tenant_date_status_idx');
        });

        // Prefix lengths keep the composite key under InnoDB's 3072-byte limit on
        // MySQL utf8mb4 (three default string() columns would exceed it with status).
        DB::statement(
            'ALTER TABLE bookings ADD INDEX bookings_queue_advance_idx '
            .'(tenant_id(36), bookable_type(191), bookable_id, booking_date, status(32), serial_number)',
        );
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
