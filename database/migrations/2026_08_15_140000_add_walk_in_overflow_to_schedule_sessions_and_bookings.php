<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('walk_in_overflow_cap')->default(0)->after('slot_cap');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('is_overflow')->default(false)->after('serial_number');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_sessions', function (Blueprint $table) {
            $table->dropColumn('walk_in_overflow_cap');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('is_overflow');
        });
    }
};
