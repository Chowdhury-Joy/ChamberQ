<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use Illuminate\View\View;

/**
 * The patient's own copy of one prescription, opened from the SMS or WhatsApp
 * link the doctor sends before the patient leaves the chamber.
 *
 * Deliberately unauthenticated — an unguessable, expiring token is the gate.
 * The view is scoped to this prescription's medicines only: no diagnosis, no
 * other visit, no route onward into the practice's records. See the 2026-08-07
 * decision in `decisions.md`.
 *
 * Two entry points reach the same view: `showByToken()` is the short `/p/{token}`
 * link now sent, and `show()` is the older temporary-signed URL, kept alive only
 * until the links already in patients' phones have expired.
 */
class PrescriptionShareController extends Controller
{
    /**
     * Short share link. The token carries no expiry of its own — the stored
     * `share_token_expires_at` is checked here, so an expired link 404s rather
     * than quietly still working.
     */
    public function showByToken(string $token): View
    {
        $prescription = Prescription::query()
            ->where('share_token', $token)
            ->where('share_token_expires_at', '>', now())
            ->firstOrFail();

        return $this->render($prescription);
    }

    /**
     * Legacy temporary-signed link. The `signed` middleware is the gate.
     */
    public function show(Prescription $prescription): View
    {
        return $this->render($prescription);
    }

    private function render(Prescription $prescription): View
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
