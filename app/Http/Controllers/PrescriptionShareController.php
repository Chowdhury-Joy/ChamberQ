<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use Illuminate\View\View;

/**
 * The patient's own copy of one prescription, opened from the WhatsApp link the
 * doctor sends before the patient leaves the chamber.
 *
 * Deliberately unauthenticated — the expiring signature on the URL is the gate.
 * The view is scoped to this prescription's medicines only: no diagnosis, no
 * other visit, no route onward into the practice's records. See the 2026-08-07
 * decision in `decisions.md`.
 */
class PrescriptionShareController extends Controller
{
    public function show(Prescription $prescription): View
    {
        // visitRecord is loaded only to reach the booking date — the share view
        // never touches its clinical fields.
        $prescription->load(['items', 'patient', 'visitRecord.booking']);

        ['doctor' => $doctor] = $prescription->resolveDoctorChamber();

        return view('tenant.prescriptions.share', [
            'prescription' => $prescription,
            'patient' => $prescription->patient,
            'booking' => $prescription->visitRecord?->booking,
            'doctor' => $doctor,
        ]);
    }
}
