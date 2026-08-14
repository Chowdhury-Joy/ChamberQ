<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Stop sharing the clinical history of patients who were never asked.
 *
 * `2026_08_13_012948_add_share_clinical_history_to_patients_table` added the
 * column with `->default(true)`, which silently backfilled **every patient row
 * that already existed** to "sharing on". Those people registered before the
 * consent checkbox existed, so their `true` is an artefact of a column default,
 * not an answer they gave — and what it opts them into is another chamber's
 * doctor reading their diagnoses and prescriptions.
 *
 * Anyone who has booked since then went through the wizard (or a walk-in modal)
 * where the checkbox is shown, and `PatientService` wrote their real answer, so
 * only rows created strictly before that migration are touched.
 *
 * Deliberately costs some cross-chamber history: those patients opt back in the
 * next time they book and are asked properly.
 */
return new class extends Migration
{
    /**
     * The moment the consent checkbox went live. Rows older than this were
     * defaulted to `true` by the schema, never by a patient.
     */
    private const CONSENT_LIVE_FROM = '2026-08-13 00:00:00';

    public function up(): void
    {
        DB::table('patients')
            ->where('created_at', '<', self::CONSENT_LIVE_FROM)
            ->where('share_clinical_history', true)
            ->update(['share_clinical_history' => false]);
    }

    public function down(): void
    {
        // Intentionally does nothing.
        //
        // Reversing this would re-assert consent that nobody gave, which is the
        // exact bug being corrected. Patients who want sharing on will be asked
        // at their next booking.
    }
};
