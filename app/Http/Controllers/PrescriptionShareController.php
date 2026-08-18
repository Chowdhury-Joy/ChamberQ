<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use App\Services\PortalPrescriptionLock;
use App\Support\BdPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The patient's own copy of one prescription.
 *
 * Opened from:
 * - the SMS/WhatsApp short link (`/p/{token}`, expires 48h)
 * - the legacy signed share URL
 * - the portal phone-gated route (durable backup if staff forget to send)
 *
 * Full clinical pad: diagnosis, notes, investigations, medicines, advice,
 * follow-up, chamber letterhead. Voice notes and prescription photos stay off.
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

        return $this->render($prescription, showExpiryFootnote: true);
    }

    /**
     * Legacy temporary-signed link. The `signed` middleware is the gate.
     */
    public function show(Prescription $prescription): View
    {
        return $this->render($prescription, showExpiryFootnote: true);
    }

    /**
     * Portal backup: phone must match the booking, then the prescription
     * password for that number must be unlocked in this session.
     */
    public function showFromPortal(Request $request, Prescription $prescription): View|RedirectResponse
    {
        $phone = $request->query('phone');

        $validator = validator(
            ['phone' => $phone],
            ['phone' => ['required', 'string', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/']],
        );

        if ($validator->fails()) {
            abort(404);
        }

        $prescription->loadMissing(['visitRecord.booking', 'items']);

        $bookingPhone = $prescription->visitRecord?->booking?->patient_phone;
        if (blank($bookingPhone)) {
            abort(404);
        }

        // Same gate as portal lookup: any common BD prefix spelling of the
        // number that booked this visit is enough.
        $normalizedBooking = BdPhone::normalize($bookingPhone);
        $allowed = collect(BdPhone::lookupVariants((string) $phone))
            ->map(fn (string $v) => BdPhone::normalize($v))
            ->push(BdPhone::normalize((string) $phone))
            ->filter()
            ->unique()
            ->all();

        if ($normalizedBooking === '' || ! in_array($normalizedBooking, $allowed, true)) {
            abort(404);
        }

        if ($prescription->items->isEmpty()) {
            abort(404);
        }

        $lock = app(PortalPrescriptionLock::class);
        if ($lock->gate($request, (string) $phone) === PortalPrescriptionLock::GATE_UNLOCK) {
            return redirect($lock->portalRedirect((string) $phone));
        }

        return $this->render($prescription, showExpiryFootnote: false);
    }

    private function render(Prescription $prescription, bool $showExpiryFootnote): View
    {
        $prescription->load([
            'items',
            'patient',
            'visitRecord.booking',
            'visitRecord.condition',
        ]);

        ['doctor' => $doctor, 'chamber' => $chamber] = $prescription->resolveDoctorChamber();

        $visit = $prescription->visitRecord;

        return view('tenant.prescriptions.share', [
            'prescription' => $prescription,
            'visitRecord' => $visit,
            'patient' => $prescription->patient,
            'booking' => $visit?->booking,
            'doctor' => $doctor,
            'chamber' => $chamber,
            'tenant' => tenant(),
            'showExpiryFootnote' => $showExpiryFootnote,
        ]);
    }
}
