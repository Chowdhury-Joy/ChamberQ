<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pulse and SpO₂ for the O/E vitals table on the Rx desk.
 *
 * Weight and BP were already first-class columns; the cleaner desk puts all
 * four measurements in one O/E table (matching the Option B mockup), so these
 * two need the same treatment rather than being typed into free-text O/E.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_records', function (Blueprint $table) {
            $table->unsignedSmallInteger('pulse_bpm')->nullable()->after('bp_diastolic');
            $table->unsignedTinyInteger('spo2_percent')->nullable()->after('pulse_bpm');
        });
    }

    public function down(): void
    {
        Schema::table('visit_records', function (Blueprint $table) {
            $table->dropColumn(['pulse_bpm', 'spo2_percent']);
        });
    }
};
