<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When OT marked this row prepped.
 *
 * `procedure_status` alone cannot tell the consult screen whether the room
 * was made ready ten seconds ago or two hours ago, so a doctor opening the
 * screen mid-afternoon would be told about every prepped row of the day.
 * The stamp bounds the announcement to a fresh one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('procedure_prepped_at')->nullable()->after('procedure_status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('procedure_prepped_at');
        });
    }
};
