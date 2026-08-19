<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chamber_cash_entries', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'booking_id']);
            $table->index(['tenant_id', 'booking_id']);
        });

        Schema::table('doctors', function (Blueprint $table) {
            $table->boolean('collect_fee_at_checkin')->nullable()->after('allows_repeat_serials');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn('collect_fee_at_checkin');
        });

        Schema::table('chamber_cash_entries', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'booking_id']);
            $table->unique(['tenant_id', 'booking_id']);
        });
    }
};
