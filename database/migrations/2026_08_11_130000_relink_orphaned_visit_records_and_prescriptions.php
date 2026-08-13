<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Re-attach visit records and prescriptions that a patient merge orphaned.
 *
 * `PatientService::mergePatients()` used to move only `bookings.patient_id`
 * before deleting the duplicate patient. `visit_records.patient_id` and
 * `prescriptions.patient_id` are `nullOnDelete` foreign keys, so both were
 * silently set to NULL — and the consult screen reads history by `patient_id`,
 * so the doctor was told "no history" for a patient whose notes were still
 * stored, with nothing pointing at them.
 *
 * The link is recoverable without guessing: a visit record knows its booking,
 * and the booking's `patient_id` was correctly moved by the merge. A
 * prescription hangs off its visit record. So both are repaired from data that
 * is already right, not inferred.
 *
 * Deliberately only touches rows where `patient_id IS NULL` — a row that still
 * has a patient is not ours to rewrite.
 */
return new class extends Migration
{
    public function up(): void
    {
        // visit_records ← bookings.patient_id
        DB::table('visit_records')
            ->whereNull('patient_id')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('bookings')
                    ->whereColumn('bookings.id', 'visit_records.booking_id')
                    ->whereNotNull('bookings.patient_id');
            })
            ->update([
                'patient_id' => DB::raw(
                    '(select b.patient_id from bookings b where b.id = visit_records.booking_id)'
                ),
            ]);

        // prescriptions ← visit_records.patient_id (now repaired above)
        DB::table('prescriptions')
            ->whereNull('patient_id')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('visit_records')
                    ->whereColumn('visit_records.id', 'prescriptions.visit_record_id')
                    ->whereNotNull('visit_records.patient_id');
            })
            ->update([
                'patient_id' => DB::raw(
                    '(select v.patient_id from visit_records v where v.id = prescriptions.visit_record_id)'
                ),
            ]);
    }

    public function down(): void
    {
        // Not reversible: the NULLs this repaired carried no information, so
        // there is nothing to restore them to. Re-nulling them would recreate
        // the data loss this migration exists to undo.
    }
};
