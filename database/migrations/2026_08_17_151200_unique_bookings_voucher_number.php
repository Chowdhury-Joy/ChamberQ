<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'booking_date', 'voucher_number']);
            $table->unique(['tenant_id', 'booking_date', 'voucher_number'], 'bookings_voucher_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique('bookings_voucher_unique');
            $table->index(['tenant_id', 'booking_date', 'voucher_number']);
        });
    }
};
