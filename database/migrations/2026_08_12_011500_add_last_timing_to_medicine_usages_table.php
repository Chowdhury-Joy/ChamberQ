<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a doctor's saved default carry the timing, not just dose/frequency/duration.
 *
 * Without this, layer 1 of the prefill chain could never override layer 2 on
 * the one field patients most often get wrong: a doctor who deliberately gives
 * a PPI after food would have had his choice silently replaced by the
 * catalogue's "before food" on every subsequent patient.
 *
 * Stores a key from App\Support\PrescriptionTiming, never free text, so it can
 * print bilingually.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicine_usages', function (Blueprint $table) {
            $table->string('last_timing', 40)->nullable()->after('last_duration');
        });
    }

    public function down(): void
    {
        Schema::table('medicine_usages', function (Blueprint $table) {
            $table->dropColumn('last_timing');
        });
    }
};
